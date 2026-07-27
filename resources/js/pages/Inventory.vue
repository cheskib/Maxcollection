<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, watch } from 'vue';

const props = defineProps<{
    pipeline: { waiting: number; queued: number; processing: number; processed: number; needsReview: number };
    uploads: {
        batches: { id: number; label: string; uploadedAt: string; itemCount: number; doneCount: number }[];
        singleCount: number;
        singleDoneCount: number;
    };
}>();

const page = usePage<{ flash: { status: string | null } }>();

const total = computed(
    () =>
        props.pipeline.waiting +
        props.pipeline.queued +
        props.pipeline.processing +
        props.pipeline.processed +
        props.pipeline.needsReview,
);

function processNow(): void {
    router.post('/process', {}, { preserveScroll: true });
}

// Keep the numbers live while anything is queued or processing.
let poll: number | null = null;

function stopPolling(): void {
    if (poll !== null) {
        clearInterval(poll);
        poll = null;
    }
}

watch(
    () => props.pipeline.queued + props.pipeline.processing,
    (active) => {
        if (active > 0 && poll === null) {
            poll = window.setInterval(() => router.reload(), 4000);
        } else if (active === 0) {
            stopPolling();
        }
    },
    { immediate: true },
);
onUnmounted(stopPolling);

function percent(done: number, count: number): number {
    return count > 0 ? Math.round((done / count) * 100) : 0;
}
</script>

<template>
    <Head title="Inventory" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Inventory</h1>
            <Link href="/" class="ml-2 shrink-0 text-sm font-semibold text-blue-600">Home</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">{{ total }} item(s) uploaded — here's where each one stands.</p>

        <p v-if="page.props.flash.status" class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">
            {{ page.props.flash.status }}
        </p>

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Pipeline</p>
        <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
            <div class="flex items-center justify-between p-4">
                <p class="text-sm text-gray-700">⬜ Waiting to process</p>
                <div class="flex items-center gap-3">
                    <span class="font-bold text-gray-900">{{ pipeline.waiting }}</span>
                    <button
                        v-if="pipeline.waiting > 0"
                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700"
                        @click="processNow"
                    >
                        ▶ Process now
                    </button>
                </div>
            </div>
            <div class="flex items-center justify-between p-4">
                <p class="text-sm text-gray-700">🔵 Queued</p>
                <span class="font-bold text-gray-900">{{ pipeline.queued }}</span>
            </div>
            <div class="flex items-center justify-between p-4">
                <p class="text-sm text-gray-700">⚙️ Processing</p>
                <span class="font-bold text-gray-900">{{ pipeline.processing }}</span>
            </div>
            <Link href="/items" class="flex items-center justify-between p-4 hover:bg-gray-50">
                <p class="text-sm text-gray-700">✅ Processed</p>
                <span class="font-bold text-gray-900">{{ pipeline.processed }} ›</span>
            </Link>
            <Link href="/review" class="flex items-center justify-between p-4 hover:bg-gray-50">
                <p class="text-sm text-gray-700">⚠️ Needs Review</p>
                <span class="font-bold" :class="pipeline.needsReview ? 'text-amber-600' : 'text-gray-900'">
                    {{ pipeline.needsReview }} ›
                </span>
            </Link>
        </div>

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Uploads</p>
        <div class="mt-2 flex flex-col gap-2">
            <Link
                v-for="batch in uploads.batches"
                :key="batch.id"
                :href="`/batches/${batch.id}`"
                class="rounded-xl bg-white p-4 shadow-sm hover:bg-gray-50"
            >
                <div class="flex items-center justify-between">
                    <p class="min-w-0 truncate text-sm font-semibold text-gray-900">{{ batch.label }}</p>
                    <p class="ml-3 shrink-0 text-xs text-gray-500">{{ batch.doneCount }}/{{ batch.itemCount }} done</p>
                </div>
                <p class="mt-0.5 text-xs text-gray-400">{{ batch.uploadedAt }} · {{ batch.itemCount }} item(s)</p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full bg-blue-600" :style="{ width: `${percent(batch.doneCount, batch.itemCount)}%` }"></div>
                </div>
            </Link>
            <div v-if="uploads.singleCount > 0" class="rounded-xl bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-gray-900">Single captures</p>
                    <p class="ml-3 shrink-0 text-xs text-gray-500">{{ uploads.singleDoneCount }}/{{ uploads.singleCount }} done</p>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full bg-blue-600" :style="{ width: `${percent(uploads.singleDoneCount, uploads.singleCount)}%` }"></div>
                </div>
            </div>
        </div>
    </div>
</template>
