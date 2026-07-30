<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import CollectionPicker, { type CollectionChoice } from '../components/CollectionPicker.vue';
import { collectionPayload } from '../composables/lastCollection';

interface BatchStatus {
    id: number;
    label: string;
    converting: boolean;
    itemCount: number;
    captured: number;
    inProgress: number;
    processed: number;
    needsReview: number;
    keyCards?: number;
}

const props = defineProps<{
    collections: { id: number; name: string }[];
    pendingBatches: BatchStatus[];
}>();

const collectionChoice = ref<CollectionChoice>({ collectionId: null, newName: '' });
const photosPerItem = ref<1 | 2>(2);
// Seeded with any batches still converting or awaiting processing, so
// leaving the page and coming back never loses them.
const batches = ref<BatchStatus[]>([...props.pendingBatches]);
const uploading = ref(false);
const processing = ref(false);
const error = ref<string | null>(null);
const notice = ref<string | null>(null);
const pdfInput = ref<HTMLInputElement | null>(null);
// Grid photos: several items laid out in equal boxes, shot from above.
const gridCells = ref<1 | 2 | 4 | 6>(6);
const gridSides = ref<1 | 2>(1);
const gridFrontInput = ref<HTMLInputElement | null>(null);
const gridBackInput = ref<HTMLInputElement | null>(null);
const gridFront = ref<File | null>(null);
const gridBack = ref<File | null>(null);
let poller: ReturnType<typeof setInterval> | null = null;

// No last-used default: the collection is chosen consciously each
// session so batches never land in the wrong one unnoticed.
onMounted(() => {
    poller = setInterval(refreshStatus, 4000);
});

const collectionChosen = computed(() => {
    const choice = collectionChoice.value;
    return typeof choice.collectionId === 'number' || (choice.collectionId === 'new' && choice.newName.trim() !== '');
});

const collectionName = computed(() => {
    const choice = collectionChoice.value;
    if (choice.collectionId === 'new') return choice.newName.trim() || null;
    return props.collections.find((entry) => entry.id === choice.collectionId)?.name ?? null;
});

onBeforeUnmount(() => {
    if (poller) clearInterval(poller);
    if (clock) clearInterval(clock);
});

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function refreshStatus(): Promise<void> {
    if (batches.value.length === 0) return;
    const ids = batches.value.map((batch) => batch.id).join(',');
    try {
        const response = await fetch(`/capture/bulk/status?ids=${ids}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const data = await response.json();
        // Preserve upload order while refreshing numbers.
        const byId = new Map((data.batches as BatchStatus[]).map((batch) => [batch.id, batch]));
        batches.value = batches.value.map((batch) => byId.get(batch.id) ?? batch);
    } catch {
        // Transient polling errors are ignored; the next tick retries.
    }
}

async function onPdf(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';
    if (!files.length) return;

    error.value = null;
    notice.value = null;
    uploading.value = true;

    try {
        for (const pdf of files) {
            const body = new FormData();
            body.append('pdf', pdf);
            body.append('photos_per_item', String(photosPerItem.value));
            Object.entries(collectionPayload(collectionChoice.value)).forEach(([key, value]) => body.append(key, value));

            const response = await fetch('/capture/bulk/pdf', {
                method: 'POST',
                headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json' },
                credentials: 'same-origin',
                body,
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message ?? `Upload failed (${response.status})`);

            if (typeof data.collectionId === 'number') {
                collectionChoice.value = { collectionId: data.collectionId, newName: '' };
            }

            batches.value.push({
                id: data.batchId,
                label: data.label ?? pdf.name,
                converting: true,
                itemCount: 0,
                captured: 0,
                inProgress: 0,
                processed: 0,
                needsReview: 0,
            });
        }
        void refreshStatus();
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Upload failed.';
    } finally {
        uploading.value = false;
    }
}

async function uploadGrid(front: File, back: File | null): Promise<void> {
    const body = new FormData();
    body.append('photo', front);
    if (back) body.append('back_photo', back);
    body.append('cells', String(gridCells.value));
    Object.entries(collectionPayload(collectionChoice.value)).forEach(([key, value]) => body.append(key, value));

    const response = await fetch('/capture/bulk/grid', {
        method: 'POST',
        headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json' },
        credentials: 'same-origin',
        body,
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message ?? `Upload failed (${response.status})`);

    if (typeof data.collectionId === 'number') {
        collectionChoice.value = { collectionId: data.collectionId, newName: '' };
    }

    batches.value.push({
        id: data.batchId,
        label: data.label ?? front.name,
        converting: true,
        itemCount: 0,
        captured: 0,
        inProgress: 0,
        processed: 0,
        needsReview: 0,
    });
}

// Front-only mode uploads immediately (multi-select welcome); front & back
// mode stages the front until its matching back is picked.
async function onGridFronts(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';
    if (!files.length) return;

    if (gridSides.value === 2) {
        gridFront.value = files[0];
        return;
    }

    error.value = null;
    notice.value = null;
    uploading.value = true;
    try {
        for (const file of files) await uploadGrid(file, null);
        void refreshStatus();
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Upload failed.';
    } finally {
        uploading.value = false;
    }
}

function onGridBack(event: Event): void {
    const input = event.target as HTMLInputElement;
    gridBack.value = Array.from(input.files ?? [])[0] ?? null;
    input.value = '';
}

async function uploadGridPair(): Promise<void> {
    if (!gridFront.value) return;

    error.value = null;
    notice.value = null;
    uploading.value = true;
    try {
        await uploadGrid(gridFront.value, gridBack.value);
        gridFront.value = null;
        gridBack.value = null;
        void refreshStatus();
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Upload failed.';
    } finally {
        uploading.value = false;
    }
}

// Live progress after pressing Process: done vs remaining, elapsed
// clock, and a rough time-left estimate from the pace so far. Progress
// counts actual completions in the batches this run queued (against a
// baseline taken at the click), so uploads or PDF conversions landing
// mid-run can never make the number move backwards.
const runStartedAt = ref<number | null>(null);
const runFinishedAt = ref<number | null>(null);
const runTotal = ref(0);
const runBatchIds = ref<number[]>([]);
const runBaseline = ref<Record<number, number>>({});
const nowTick = ref(Date.now());
let clock: ReturnType<typeof setInterval> | null = null;

const progress = computed(() => {
    if (runStartedAt.value === null) return null;
    const done = Math.min(
        runTotal.value,
        batches.value
            .filter((batch) => runBatchIds.value.includes(batch.id))
            .reduce((sum, batch) => {
                const finishedNow = batch.processed + batch.needsReview;
                return sum + Math.max(0, finishedNow - (runBaseline.value[batch.id] ?? 0));
            }, 0),
    );
    const remaining = runTotal.value - done;
    const elapsedMs = (runFinishedAt.value ?? nowTick.value) - runStartedAt.value;
    const format = (ms: number) => {
        const seconds = Math.round(ms / 1000);
        return seconds >= 60 ? `${Math.floor(seconds / 60)}m ${seconds % 60}s` : `${seconds}s`;
    };
    const eta = done > 0 && remaining > 0 ? format((elapsedMs / done) * remaining) : null;
    return {
        done,
        remaining,
        total: runTotal.value,
        percent: runTotal.value > 0 ? Math.round((done / runTotal.value) * 100) : 0,
        elapsed: format(elapsedMs),
        eta,
        finished: remaining === 0,
    };
});

// Freeze the clock the moment the run completes.
watch(progress, (current) => {
    if (current?.finished && runFinishedAt.value === null) {
        runFinishedAt.value = Date.now();
    }
});

// Remove a stuck or mistaken batch entirely (items and files included).
function removeBatch(batch: BatchStatus): void {
    const what = batch.itemCount > 0 ? `"${batch.label}" and its ${batch.itemCount} card(s)` : `"${batch.label}"`;
    if (!confirm(`Delete ${what}? This cannot be undone.`)) return;
    router.delete(`/batches/${batch.id}`, {
        onSuccess: () => {
            batches.value = batches.value.filter((entry) => entry.id !== batch.id);
        },
    });
}

async function processAll(): Promise<void> {
    const ready = batches.value.filter((batch) => batch.captured > 0).map((batch) => batch.id);
    if (!ready.length) return;

    processing.value = true;
    error.value = null;
    try {
        const response = await fetch('/capture/bulk/process', {
            method: 'POST',
            headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json', 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ batch_ids: ready }),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message ?? `Request failed (${response.status})`);
        notice.value = `${data.queued} item(s) queued for AI processing.`;
        runStartedAt.value = Date.now();
        runFinishedAt.value = null;
        runTotal.value = data.queued;
        runBatchIds.value = ready;
        runBaseline.value = Object.fromEntries(
            batches.value
                .filter((batch) => ready.includes(batch.id))
                .map((batch) => [batch.id, batch.processed + batch.needsReview]),
        );
        if (clock === null) clock = setInterval(() => (nowTick.value = Date.now()), 1000);
        void refreshStatus();
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Processing request failed.';
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Head title="Bulk Capture" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Bulk Capture</h1>
            <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">Upload scanner PDFs or grid photos — each becomes its own batch below.</p>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-gray-700">First — which collection do these go in?</p>
            <div class="mt-2">
                <CollectionPicker v-model="collectionChoice" :collections="collections" />
            </div>
            <p class="mt-3 text-sm font-medium text-gray-700">Pages per card</p>
            <div class="mt-2 flex gap-2">
                <button
                    :class="photosPerItem === 2 ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                    class="flex-1 rounded-lg py-2 text-sm font-semibold"
                    @click="photosPerItem = 2"
                >
                    2 — front &amp; back
                </button>
                <button
                    :class="photosPerItem === 1 ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                    class="flex-1 rounded-lg py-2 text-sm font-semibold"
                    @click="photosPerItem = 1"
                >
                    1 — front only
                </button>
            </div>
        </div>

        <input ref="pdfInput" type="file" accept="application/pdf" multiple class="hidden" @change="onPdf" />
        <p v-if="collectionChosen && collectionName" class="mt-4 text-center text-sm text-gray-600">
            Everything below goes into <span class="font-bold text-gray-900">{{ collectionName }}</span>
        </p>
        <button
            :disabled="uploading || !collectionChosen"
            class="mt-2 w-full rounded-xl bg-blue-600 py-4 font-semibold text-white shadow-sm hover:bg-blue-700 disabled:bg-gray-300"
            @click="pdfInput?.click()"
        >
            {{ uploading ? 'Uploading…' : collectionChosen ? '⬆ Add Batch PDF(s)' : 'Select a collection first' }}
        </button>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-gray-700">📐 Grid photo — several items in one shot</p>
            <p class="mt-1 text-xs text-gray-500">
                Lay items in equal boxes, shoot straight down. Each box becomes its own item.
            </p>

            <p class="mt-3 text-sm font-medium text-gray-700">Items per photo</p>
            <div class="mt-2 flex gap-2">
                <button
                    v-for="count in ([1, 2, 4, 6] as const)"
                    :key="count"
                    :class="gridCells === count ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                    class="flex-1 rounded-lg py-2 text-sm font-semibold"
                    @click="gridCells = count"
                >
                    {{ count }}
                </button>
            </div>

            <p class="mt-3 text-sm font-medium text-gray-700">Sides</p>
            <div class="mt-2 flex gap-2">
                <button
                    :class="gridSides === 1 ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                    class="flex-1 rounded-lg py-2 text-sm font-semibold"
                    @click="gridSides = 1"
                >
                    1 — front only (comics)
                </button>
                <button
                    :class="gridSides === 2 ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                    class="flex-1 rounded-lg py-2 text-sm font-semibold"
                    @click="gridSides = 2"
                >
                    2 — front &amp; back (cards)
                </button>
            </div>

            <input ref="gridFrontInput" type="file" accept="image/*" :multiple="gridSides === 1" class="hidden" @change="onGridFronts" />
            <input ref="gridBackInput" type="file" accept="image/*" class="hidden" @change="onGridBack" />

            <template v-if="gridSides === 1">
                <button
                    :disabled="uploading || !collectionChosen"
                    class="mt-4 w-full rounded-xl bg-blue-600 py-3 font-semibold text-white shadow-sm hover:bg-blue-700 disabled:bg-gray-300"
                    @click="gridFrontInput?.click()"
                >
                    {{ uploading ? 'Uploading…' : collectionChosen ? '⬆ Add Grid Photo(s)' : 'Select a collection first' }}
                </button>
            </template>

            <template v-else>
                <p class="mt-3 text-xs text-gray-500">
                    Shoot the fronts, flip every card in its own box, shoot again — boxes pair up by position.
                </p>
                <div class="mt-2 flex gap-2">
                    <button
                        :disabled="uploading || !collectionChosen"
                        :class="gridFront ? 'border-green-500 text-green-700' : 'border-gray-300 text-gray-700'"
                        class="flex-1 rounded-lg border-2 py-3 text-sm font-semibold disabled:opacity-50"
                        @click="gridFrontInput?.click()"
                    >
                        {{ gridFront ? '✓ Fronts photo' : '1. Fronts photo' }}
                    </button>
                    <button
                        :disabled="uploading || !collectionChosen"
                        :class="gridBack ? 'border-green-500 text-green-700' : 'border-gray-300 text-gray-700'"
                        class="flex-1 rounded-lg border-2 py-3 text-sm font-semibold disabled:opacity-50"
                        @click="gridBackInput?.click()"
                    >
                        {{ gridBack ? '✓ Backs photo' : '2. Backs photo' }}
                    </button>
                </div>
                <button
                    :disabled="uploading || !collectionChosen || !gridFront"
                    class="mt-2 w-full rounded-xl bg-blue-600 py-3 font-semibold text-white shadow-sm hover:bg-blue-700 disabled:bg-gray-300"
                    @click="uploadGridPair"
                >
                    {{ uploading ? 'Uploading…' : '⬆ Upload Grid' }}
                </button>
            </template>
        </div>

        <p v-if="error" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <p v-if="notice" class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ notice }}</p>

        <div v-if="progress" class="mt-3 rounded-xl border border-blue-200 bg-blue-50 p-4">
            <template v-if="!progress.finished">
                <p class="text-sm font-semibold text-gray-900">
                    ⚙️ Processing… {{ progress.done }} of {{ progress.total }} done · {{ progress.remaining }} remaining
                </p>
                <p class="mt-0.5 text-xs text-gray-500">
                    Elapsed {{ progress.elapsed }}<span v-if="progress.eta"> · about {{ progress.eta }} left</span>
                </p>
            </template>
            <p v-else class="text-sm font-semibold text-green-700">✅ All {{ progress.total }} item(s) processed in {{ progress.elapsed }}.</p>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-white">
                <div class="h-full bg-blue-600 transition-all" :style="{ width: `${progress.percent}%` }"></div>
            </div>
        </div>

        <div v-if="batches.length" class="mt-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">This session's batches</p>
            <div class="mt-2 flex flex-col gap-3">
                <div v-for="batch in batches" :key="batch.id" class="rounded-xl bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="min-w-0 truncate font-semibold text-gray-900">{{ batch.label }}</p>
                        <div class="ml-2 flex shrink-0 gap-3">
                            <Link :href="`/batches/${batch.id}`" class="text-sm font-semibold text-blue-600">View</Link>
                            <button
                                class="text-sm font-semibold text-red-500 hover:text-red-700"
                                title="Delete this batch and its cards"
                                @click="removeBatch(batch)"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                    <p v-if="batch.converting" class="mt-1 text-sm text-gray-500">⏳ Converting into items…</p>
                    <div v-else class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <span class="text-gray-700">{{ batch.itemCount }} item(s)</span>
                        <span v-if="batch.captured" class="text-blue-700">{{ batch.captured }} ready to process</span>
                        <span v-if="batch.inProgress" class="text-gray-500">{{ batch.inProgress }} processing…</span>
                        <span v-if="batch.processed" class="text-green-700">{{ batch.processed }} processed</span>
                        <span v-if="batch.needsReview" class="text-amber-700">{{ batch.needsReview }} needs review</span>
                        <span v-if="batch.keyCards" class="font-semibold text-yellow-600">⭐ {{ batch.keyCards }} possible key card(s)</span>
                    </div>
                </div>
            </div>

            <button
                :disabled="processing || !batches.some((batch) => batch.captured > 0)"
                class="mt-4 w-full rounded-xl bg-green-600 py-4 font-semibold text-white shadow-sm hover:bg-green-700 disabled:bg-gray-300"
                @click="processAll"
            >
                ▶ Process Batch(es)
            </button>
        </div>

        <p v-else class="mt-8 text-center text-sm text-gray-400">No batches yet this session — add a PDF or grid photo to begin.</p>
    </div>
</template>
