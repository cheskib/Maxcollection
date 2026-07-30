<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    stats: {
        itemsCaptured: number;
        itemsProcessed: number;
        needsReview: number;
        picturesUploaded: number;
        unprocessed: number;
        value: { from: number | null; to: number | null; valuedCount: number };
    };
}>();

function money(from: number | null, to: number | null): string | null {
    if (from === null && to === null) return null;
    const fmt = (v: number) => `$${v.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
    if (from !== null && to !== null) return from === to ? fmt(from) : `${fmt(from)} – ${fmt(to)}`;
    return fmt((from ?? to) as number);
}

const page = usePage<{ flash: { status: string | null }; auth: { isAdmin: boolean } }>();

// Live progress after pressing Process: completions counted against a
// baseline taken at the click, so the number can never move backwards.
const runStartedAt = ref<number | null>(null);
const runFinishedAt = ref<number | null>(null);
const runTotal = ref(0);
const runBaselineDone = ref(0);
const nowTick = ref(Date.now());
let clock: ReturnType<typeof setInterval> | null = null;
let poll: ReturnType<typeof setInterval> | null = null;

function stopTimers(): void {
    if (clock) clearInterval(clock);
    if (poll) clearInterval(poll);
    clock = poll = null;
}
onUnmounted(stopTimers);

const progress = computed(() => {
    if (runStartedAt.value === null || runTotal.value === 0) return null;
    const doneNow = props.stats.itemsProcessed + props.stats.needsReview - runBaselineDone.value;
    const done = Math.min(runTotal.value, Math.max(0, doneNow));
    const remaining = runTotal.value - done;
    const elapsedMs = (runFinishedAt.value ?? nowTick.value) - runStartedAt.value;
    const format = (ms: number) => {
        const seconds = Math.round(ms / 1000);
        return seconds >= 60 ? `${Math.floor(seconds / 60)}m ${seconds % 60}s` : `${seconds}s`;
    };
    return {
        done,
        remaining,
        total: runTotal.value,
        percent: Math.round((done / runTotal.value) * 100),
        elapsed: format(elapsedMs),
        eta: done > 0 && remaining > 0 ? format((elapsedMs / done) * remaining) : null,
        finished: remaining === 0,
    };
});

watch(progress, (current) => {
    if (current?.finished && runFinishedAt.value === null) {
        runFinishedAt.value = Date.now();
        stopTimers();
    }
});

function processItems(): void {
    const total = props.stats.unprocessed;
    const baseline = props.stats.itemsProcessed + props.stats.needsReview;

    router.post('/process', {}, {
        preserveScroll: true,
        onSuccess: () => {
            runTotal.value = total;
            runBaselineDone.value = baseline;
            runStartedAt.value = Date.now();
            runFinishedAt.value = null;
            stopTimers();
            clock = setInterval(() => (nowTick.value = Date.now()), 1000);
            poll = setInterval(() => router.reload({ only: ['stats'] }), 4000);
        },
    });
}

// Re-run the AI over the whole collection, asking which photos it reads.
const choosingReprocessSource = ref(false);

function runReprocessAll(source: 'original' | 'cleaned'): void {
    choosingReprocessSource.value = false;
    router.post('/reprocess-all', { source });
}

function logout(): void {
    router.post('/logout');
}
</script>

<template>
    <Head title="Home" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <h1 class="text-center text-2xl font-bold text-gray-900">Max Collection</h1>

        <p v-if="page.props.flash.status" class="mt-4 rounded-lg bg-green-50 p-3 text-center text-sm text-green-700">
            {{ page.props.flash.status }}
        </p>

<!-- The card inventory at a glance: a distinct panel, every tile opens
     its summary view -->
        <div class="mt-6 rounded-2xl border border-blue-100 bg-blue-50/60 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Inventory Overview</p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <Link href="/inventory" class="rounded-xl bg-white p-4 shadow-sm hover:bg-gray-50">
                    <p class="text-3xl font-bold text-gray-900">{{ stats.itemsCaptured }}</p>
                    <p class="mt-1 text-sm text-gray-500">Items Uploaded ›</p>
                </Link>
                <Link href="/items/summary" class="rounded-xl bg-white p-4 shadow-sm hover:bg-gray-50">
                    <p class="text-3xl font-bold text-gray-900">{{ stats.itemsProcessed }}</p>
                    <p class="mt-1 text-sm text-gray-500">Items Processed ›</p>
                </Link>
                <Link href="/review" class="rounded-xl bg-white p-4 shadow-sm hover:bg-gray-50">
                    <p class="text-3xl font-bold" :class="stats.needsReview ? 'text-amber-600' : 'text-gray-900'">{{ stats.needsReview }}</p>
                    <p class="mt-1 text-sm text-gray-500">Needs Review ›</p>
                </Link>
                <Link href="/photos" class="rounded-xl bg-white p-4 shadow-sm hover:bg-gray-50">
                    <p class="text-3xl font-bold text-gray-900">{{ stats.picturesUploaded }}</p>
                    <p class="mt-1 text-sm text-gray-500">Photos Uploaded ›</p>
                </Link>
            </div>
        </div>

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Add to collection</p>
        <div class="mt-2 grid grid-cols-2 gap-3">
            <Link href="/capture" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">📷</span>
                <p class="mt-1 font-semibold text-gray-900">Capture Item</p>
                <p class="text-xs text-gray-500">One at a time</p>
            </Link>
            <Link href="/capture/bulk" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">🗃️</span>
                <p class="mt-1 font-semibold text-gray-900">Bulk Capture</p>
                <p class="text-xs text-gray-500">Photos or scanner PDF</p>
            </Link>
        </div>

        <button
            class="mt-3 w-full rounded-xl py-4 font-semibold text-white shadow-sm"
            :class="stats.unprocessed ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-300'"
            :disabled="!stats.unprocessed"
            @click="processItems"
        >
            <template v-if="stats.unprocessed">▶ Process {{ stats.unprocessed }} waiting item(s)</template>
            <template v-else>Nothing waiting to process</template>
        </button>

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

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Your collection</p>
        <div class="mt-2 grid grid-cols-2 gap-3">
            <Link href="/items/summary" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">🗂️</span>
                <p class="mt-1 font-semibold text-gray-900">Processed Items</p>
            </Link>
            <Link href="/browse" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">⚾</span>
                <p class="mt-1 font-semibold text-gray-900">Browse by Sport</p>
            </Link>
            <Link href="/review" class="relative rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">🔍</span>
                <p class="mt-1 font-semibold text-gray-900">Needs Review</p>
                <span
                    v-if="stats.needsReview"
                    class="absolute right-3 top-3 rounded-full bg-amber-500 px-2 py-0.5 text-xs font-bold text-white"
                >
                    {{ stats.needsReview }}
                </span>
            </Link>
            <Link href="/batches" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">📦</span>
                <p class="mt-1 font-semibold text-gray-900">Batches</p>
            </Link>
            <Link href="/stats" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">📊</span>
                <p class="mt-1 font-semibold text-gray-900">Stats</p>
            </Link>
            <Link href="/bagging" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">🧤</span>
                <p class="mt-1 font-semibold text-gray-900">Bagging</p>
            </Link>
            <Link href="/storage" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">🏷️</span>
                <p class="mt-1 font-semibold text-gray-900">Storage</p>
            </Link>
            <Link v-if="page.props.auth.isAdmin" href="/diagnose" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">🔎</span>
                <p class="mt-1 font-semibold text-gray-900">Diagnose</p>
            </Link>
            <Link v-if="page.props.auth.isAdmin" href="/reports" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">📈</span>
                <p class="mt-1 font-semibold text-gray-900">Reports</p>
            </Link>
            <Link href="/collections" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">🗄️</span>
                <p class="mt-1 font-semibold text-gray-900">Collections</p>
                <template v-if="money(stats.value.from, stats.value.to)">
                    <p class="mt-1 text-sm font-semibold text-green-700">{{ money(stats.value.from, stats.value.to) }}</p>
                    <p class="text-xs text-gray-400">{{ stats.value.valuedCount }} item(s) valued</p>
                </template>
            </Link>
        </div>

        <template v-if="page.props.auth.isAdmin">
        <p class="mt-8 text-xs font-semibold uppercase tracking-wide text-gray-400">Maintenance</p>
        <button
            class="mt-2 w-full rounded-xl bg-gray-200 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-300"
            @click="choosingReprocessSource = !choosingReprocessSource"
        >
            ↻ Reprocess Everything
        </button>
        <div v-if="choosingReprocessSource" class="mt-2 rounded-xl border border-blue-200 bg-blue-50 p-4">
            <p class="text-sm font-semibold text-gray-900">Which photos should the AI read?</p>
            <p class="mt-1 text-xs text-gray-500">Every item will be re-run at the standard tier; current details will be replaced.</p>
            <div class="mt-3 flex flex-col gap-2">
                <button
                    class="rounded-lg bg-blue-600 px-3 py-2 text-left text-sm font-semibold text-white hover:bg-blue-700"
                    @click="runReprocessAll('cleaned')"
                >
                    Cleaned photos
                    <span class="block text-xs font-normal text-blue-100">Keep all adjustments — just re-read every item as shown.</span>
                </button>
                <button
                    class="rounded-lg bg-blue-600 px-3 py-2 text-left text-sm font-semibold text-white hover:bg-blue-700"
                    @click="runReprocessAll('original')"
                >
                    Original photos
                    <span class="block text-xs font-normal text-blue-100">Start fresh — clears hand-trims; photos return to original framing.</span>
                </button>
                <button
                    class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100"
                    @click="choosingReprocessSource = false"
                >
                    Cancel
                </button>
            </div>
        </div>
        <button
            class="mt-2 w-full rounded-xl bg-gray-200 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-300"
            @click="router.post('/revalue-all')"
        >
            💲 Update AI Values
        </button>
        </template>

        <div class="mt-8 flex items-center justify-center gap-6 text-sm">
            <Link v-if="page.props.auth.isAdmin" href="/settings" class="font-medium text-gray-500 hover:text-gray-700">Settings</Link>
            <button class="font-medium text-gray-500 hover:text-gray-700" @click="logout">Logout</button>
        </div>
    </div>
</template>
