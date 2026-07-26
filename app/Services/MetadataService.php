<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Metadata;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MetadataService
{
    /**
     * Apply user corrections. Every changed field is recorded in the
     * append-only history (PROJECT.md 15-16), and the item leaves the
     * Needs Review queue (PROJECT.md 17).
     *
     * @param array<string, string|null> $values field name => new value
     */
    public function update(Item $item, User $user, string $category, array $values): void
    {
        DB::transaction(function () use ($item, $user, $category, $values) {
            $metadata = $item->metadata()->firstOrCreate([]);

            $changes = [];

            if ($metadata->category !== $category) {
                $changes['category'] = $category;
            }

            foreach (Metadata::CATEGORY_FIELDS[$category] ?? [] as $field) {
                if (! array_key_exists($field, $values)) {
                    continue;
                }

                $new = $values[$field];
                $new = $new !== null && trim($new) !== '' ? trim($new) : null;

                if ($metadata->{$field} !== $new) {
                    $changes[$field] = $new;
                }
            }

            foreach ($changes as $field => $new) {
                $item->metadataHistory()->create([
                    'user_id' => $user->id,
                    'field_name' => $field,
                    'previous_value' => $metadata->{$field},
                    'new_value' => $new,
                ]);
            }

            if ($changes !== []) {
                $metadata->fill($changes)->save();
            }

            // A reviewed and saved item is corrected by definition.
            $item->update([
                'status' => Item::STATUS_PROCESSED,
                'review_reason' => null,
                'processed_at' => $item->processed_at ?? now(),
            ]);
        });
    }
}
