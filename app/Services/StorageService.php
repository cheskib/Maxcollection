<?php

namespace App\Services;

use App\Models\Barcode;
use App\Models\Batch;
use App\Models\Item;
use App\Models\StorageBox;
use App\Models\StorageEvent;
use App\Models\StorageSection;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The physical storage workflows: label generation, the barcode-driven
 * packing flow (box → bags → divider), undo, box completion, and batch
 * finalization (bag assignment). Every action is recorded in
 * storage_events so a physical mistake can be traced later.
 */
class StorageService
{
    /** Seconds within which the same code from the same user is a double-read. */
    private const DUPLICATE_SCAN_SECONDS = 3;

    public function __construct(private readonly DropboxService $dropbox)
    {
    }

    // ---------------------------------------------------------------
    // Labels
    // ---------------------------------------------------------------

    /**
     * Register a run of new labels. Codes are sequential per type and
     * exist in the registry from this moment — a scanned code that is
     * not registered is always a misread or a rogue label.
     *
     * @param  array<int, string>  $names divider card names (divider type only)
     * @return string the print run id
     */
    public function generateLabels(string $type, int $count, array $names = []): string
    {
        $run = (string) Str::uuid();
        $prefix = Barcode::PREFIXES[$type];

        $last = Barcode::where('type', $type)->max('code');
        $next = $last !== null ? ((int) substr($last, 4)) + 1 : 1;

        foreach (range(1, $count) as $i) {
            Barcode::create([
                'type' => $type,
                'code' => sprintf('%s-%06d', $prefix, $next++),
                'label' => $type === Barcode::TYPE_DIVIDER ? ($names[$i - 1] ?? null) : null,
                'print_run' => $run,
                'printed_at' => now(),
            ]);
        }

        return $run;
    }

    // ---------------------------------------------------------------
    // Scanning
    // ---------------------------------------------------------------

    /**
     * Handle one barcode read from the packing screen. Routing is decided
     * by the registered type of the code — never by the operator.
     *
     * @return array{ok: bool, tone: string, message: string}
     */
    public function scan(User $user, string $raw): array
    {
        $code = $this->normalize($raw);

        if ($code === null) {
            return $this->error('Not a valid barcode: "'.Str::limit(trim($raw), 24).'". Expected BAG-, BOX- or DIV- followed by 6 digits.');
        }

        $barcode = Barcode::where('code', $code)->first();

        if ($barcode === null) {
            return $this->error("Unknown barcode {$code} — it is not in the registry.");
        }

        if ($this->isDuplicateRead($user, $barcode)) {
            return ['ok' => true, 'tone' => 'info', 'message' => "Duplicate read of {$code} ignored."];
        }

        return match ($barcode->type) {
            Barcode::TYPE_BOX => $this->scanBox($user, $barcode),
            Barcode::TYPE_BAG => $this->scanBag($user, $barcode),
            Barcode::TYPE_DIVIDER => $this->scanDivider($user, $barcode),
            default => $this->error("Barcode {$code} has an unsupported type."),
        };
    }

    /**
     * Normalize a scanner read: strip whitespace/line endings the scanner
     * adds, uppercase, and require the exact label format.
     */
    public function normalize(string $raw): ?string
    {
        $code = strtoupper((string) preg_replace('/\s+/', '', $raw));

        return preg_match('/^(BAG|BOX|DIV)-\d{6}$/', $code) === 1 ? $code : null;
    }

    private function isDuplicateRead(User $user, Barcode $barcode): bool
    {
        // Only packing-screen scans count: a scanner bounce repeats the
        // same action. A bag_assigned from the batch page must never
        // swallow the first packing scan of that same bag.
        return StorageEvent::where('user_id', $user->id)
            ->where('barcode_id', $barcode->id)
            ->whereIn('action', [StorageEvent::BOX_OPENED, StorageEvent::BAG_ADDED, StorageEvent::DIVIDER_SCANNED])
            ->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_SCAN_SECONDS))
            ->exists();
    }

    private function scanBox(User $user, Barcode $barcode): array
    {
        $existing = StorageBox::where('barcode_id', $barcode->id)->first();

        if ($existing !== null) {
            if ($existing->status === StorageBox::STATUS_CLOSED) {
                return $this->error("Box {$barcode->code} was completed on {$existing->closed_at->format('M j, Y')} and is sealed.");
            }

            return $existing->user_id === $user->id
                ? ['ok' => true, 'tone' => 'info', 'message' => "Box {$barcode->code} is already open. Scan bags."]
                : $this->error("Box {$barcode->code} is being packed by another user.");
        }

        $open = $this->openBoxFor($user);
        if ($open !== null) {
            return $this->error("Complete box {$open->barcode->code} before opening another.");
        }

        $box = StorageBox::create(['user_id' => $user->id, 'barcode_id' => $barcode->id]);
        $box->sections()->create(['position' => 1]);
        $this->record($user, StorageEvent::BOX_OPENED, $barcode, box: $box);

        return $this->success("Box {$barcode->code} opened. Scan bags, then the section's divider.");
    }

    private function scanBag(User $user, Barcode $barcode): array
    {
        $box = $this->openBoxFor($user);
        if ($box === null) {
            return $this->error('Scan a BOX barcode first to start packing.');
        }

        $batch = Batch::where('barcode_id', $barcode->id)->first();
        if ($batch === null) {
            return $this->error("Bag {$barcode->code} has not been assigned to a batch yet. Finalize the batch first.");
        }

        if ($batch->storage_section_id !== null) {
            $where = $batch->storageSection->box->barcode->code;

            return $this->error("Bag {$barcode->code} is already in box {$where}.");
        }

        $section = $this->ensurePendingSection($box);
        $batch->update(['storage_section_id' => $section->id]);
        $this->record($user, StorageEvent::BAG_ADDED, $barcode, box: $box, section: $section, batch: $batch);

        $count = $section->batches()->count();

        return $this->success("Bag {$barcode->code} added — {$count} bag(s) in this section.");
    }

    private function scanDivider(User $user, Barcode $barcode): array
    {
        $box = $this->openBoxFor($user);
        if ($box === null) {
            return $this->error('Scan a BOX barcode first to start packing.');
        }

        // A physical divider exists in exactly one place.
        $used = StorageSection::where('divider_barcode_id', $barcode->id)->first();
        if ($used !== null) {
            return $this->error("Divider {$barcode->code} is already used in box {$used->box->barcode->code}.");
        }

        $section = $this->ensurePendingSection($box);
        $bags = $section->batches()->count();
        if ($bags === 0) {
            return $this->error("No bags scanned yet — scan the section's bags before its divider.");
        }

        $section->update(['divider_barcode_id' => $barcode->id]);
        $box->sections()->create(['position' => $section->position + 1]);
        $this->record($user, StorageEvent::DIVIDER_SCANNED, $barcode, box: $box, section: $section);

        return $this->success("Section {$section->position} closed as \"{$barcode->displayLabel()}\" with {$bags} bag(s). Next section started.");
    }

    // ---------------------------------------------------------------
    // Undo
    // ---------------------------------------------------------------

    /**
     * Revert the user's most recent packing scan, when doing so does not
     * disturb a completed structure. Single-level by design: predictable
     * at a noisy packing desk.
     */
    public function undoLastScan(User $user): array
    {
        // The most recent packing scan that has not itself been undone —
        // so pressing Undo repeatedly walks back scan by scan.
        $event = StorageEvent::where('user_id', $user->id)
            ->whereIn('action', [StorageEvent::BOX_OPENED, StorageEvent::BAG_ADDED, StorageEvent::DIVIDER_SCANNED])
            ->latest('id')
            ->limit(20)
            ->get()
            ->first(fn (StorageEvent $candidate) => ! StorageEvent::where('action', StorageEvent::SCAN_UNDONE)
                ->where('barcode_id', $candidate->barcode_id)
                ->where('id', '>', $candidate->id)
                ->exists());

        if ($event === null) {
            return $this->error('Nothing to undo.');
        }

        $box = $event->storage_box_id !== null ? StorageBox::find($event->storage_box_id) : null;
        if ($box === null || $box->status !== StorageBox::STATUS_OPEN) {
            return $this->error('Nothing to undo — the last scanned box is already completed.');
        }

        $code = $event->barcode->code;

        if ($event->action === StorageEvent::BAG_ADDED) {
            $batch = Batch::find($event->batch_id);
            if ($batch === null || $batch->storage_section_id !== $event->storage_section_id) {
                return $this->error('Nothing to undo.');
            }
            $batch->update(['storage_section_id' => null]);
            $this->record($user, StorageEvent::SCAN_UNDONE, $event->barcode, box: $box, batch: $batch);

            return $this->success("Undone — bag {$code} removed from the box.");
        }

        if ($event->action === StorageEvent::DIVIDER_SCANNED) {
            $section = StorageSection::find($event->storage_section_id);
            $pending = $box->pendingSection();
            if ($section === null || $section->divider_barcode_id !== $event->barcode_id
                || $pending === null || $pending->batches()->count() > 0) {
                return $this->error('Nothing to undo — bags were already scanned into the next section.');
            }
            $pending->delete();
            $section->update(['divider_barcode_id' => null]);
            $this->record($user, StorageEvent::SCAN_UNDONE, $event->barcode, box: $box, section: $section);

            return $this->success("Undone — divider {$code} removed; its section is open again.");
        }

        // BOX_OPENED: removable only while nothing has been packed into it.
        $bags = Batch::whereIn('storage_section_id', $box->sections()->pluck('id'))->count();
        if ($bags > 0) {
            return $this->error('Nothing to undo — the box already contains bags.');
        }
        $box->delete();
        $this->record($user, StorageEvent::SCAN_UNDONE, $event->barcode);

        return $this->success("Undone — box {$code} removed. Its label can be scanned again.");
    }

    // ---------------------------------------------------------------
    // Box completion
    // ---------------------------------------------------------------

    /**
     * Seal the user's open box. An empty pending section is discarded
     * silently; a pending section that still holds bags requires explicit
     * confirmation and is kept as "No Divider Assigned".
     */
    public function completeBox(User $user, bool $confirmed = false): array
    {
        $box = $this->openBoxFor($user);
        if ($box === null) {
            return $this->error('No box is open.');
        }

        // Refuse an empty box before touching anything, so a failed
        // completion never alters the box's sections.
        if (Batch::whereIn('storage_section_id', $box->sections()->pluck('id'))->count() === 0) {
            return $this->error('The box is empty — scan bags before completing it.');
        }

        $pending = $box->pendingSection();
        if ($pending !== null) {
            if ($pending->batches()->count() === 0) {
                $pending->delete();
            } elseif (! $confirmed) {
                return [
                    'ok' => false, 'tone' => 'confirm',
                    'message' => 'The last section has bags but no divider. Complete anyway?',
                ];
            }
        }

        $sectionIds = $box->sections()->pluck('id');
        $bagCount = Batch::whereIn('storage_section_id', $sectionIds)->count();

        $box->update([
            'status' => StorageBox::STATUS_CLOSED,
            'closed_at' => now(),
            'bag_count' => $bagCount,
            'section_count' => $sectionIds->count(),
            'card_count' => Item::whereIn('batch_id', Batch::whereIn('storage_section_id', $sectionIds)->pluck('id'))->count(),
        ]);
        $this->record($user, StorageEvent::BOX_COMPLETED, $box->barcode, box: $box);

        return $this->success("Box {$box->barcode->code} completed: {$box->bag_count} bag(s), {$box->section_count} section(s), {$box->card_count} card(s). Seal it.");
    }

    // ---------------------------------------------------------------
    // Batch finalization (bag assignment)
    // ---------------------------------------------------------------

    /**
     * Give a batch its permanent bag identity. Blocks on genuinely
     * incomplete work (unprocessed or in-flight items); Needs Review does
     * not block — review can happen after the cards are bagged.
     */
    public function assignBag(User $user, Batch $batch, string $raw): array
    {
        $code = $this->normalize($raw);
        if ($code === null) {
            return $this->error('Not a valid barcode. Expected a BAG-xxxxxx label.');
        }

        $barcode = Barcode::where('code', $code)->first();
        if ($barcode === null) {
            return $this->error("Unknown barcode {$code} — it is not in the registry.");
        }
        if ($barcode->type !== Barcode::TYPE_BAG) {
            return $this->error("{$code} is not a bag barcode.");
        }

        $taken = Batch::where('barcode_id', $barcode->id)->where('id', '!=', $batch->id)->first();
        if ($taken !== null) {
            return $this->error("Bag {$code} is already assigned to {$taken->displayLabel()}.");
        }

        // Re-assignment is allowed only while the bag is not sealed inside
        // a completed box.
        if ($batch->storage_section_id !== null
            && $batch->storageSection->box->status === StorageBox::STATUS_CLOSED) {
            return $this->error("This batch is sealed in box {$batch->storageSection->box->barcode->code} — reassigning its bag is not allowed.");
        }

        if ($batch->items()->count() === 0) {
            return $this->error('This batch has no items.');
        }

        $unfinished = $batch->items()->whereIn('status', [
            Item::STATUS_CAPTURED, Item::STATUS_QUEUED, Item::STATUS_PROCESSING,
        ])->count();
        if ($unfinished > 0) {
            return $this->error("{$unfinished} item(s) are not processed yet — process the batch before finalizing.");
        }

        $batch->update([
            'barcode_id' => $barcode->id,
            'status' => Batch::STATUS_CLOSED,
            'finalized_at' => $batch->finalized_at ?? now(),
            // A (re)assigned identity always archives fresh under its name.
            'archived_at' => null,
        ]);
        $this->record($user, StorageEvent::BAG_ASSIGNED, $barcode, batch: $batch);

        if ($this->dropbox->connected()) {
            \App\Jobs\ArchiveBatchJob::dispatch($batch->id);
        }

        return $this->success("Batch finalized as {$code}. Place the cards in the bag and seal it.");
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    public function openBoxFor(User $user): ?StorageBox
    {
        return StorageBox::where('user_id', $user->id)->where('status', StorageBox::STATUS_OPEN)->first();
    }

    /**
     * An open box must always have a section accepting bags; recreate it
     * if missing rather than fail a scan.
     */
    private function ensurePendingSection(StorageBox $box): StorageSection
    {
        return $box->pendingSection()
            ?? $box->sections()->create(['position' => ((int) $box->sections()->max('position')) + 1]);
    }

    private function record(
        User $user,
        string $action,
        ?Barcode $barcode,
        ?StorageBox $box = null,
        ?StorageSection $section = null,
        ?Batch $batch = null,
    ): void {
        StorageEvent::create([
            'user_id' => $user->id,
            'action' => $action,
            'barcode_id' => $barcode?->id,
            'storage_box_id' => $box?->id,
            'storage_section_id' => $section?->id,
            'batch_id' => $batch?->id,
        ]);
    }

    private function success(string $message): array
    {
        return ['ok' => true, 'tone' => 'success', 'message' => $message];
    }

    private function error(string $message): array
    {
        return ['ok' => false, 'tone' => 'error', 'message' => $message];
    }
}
