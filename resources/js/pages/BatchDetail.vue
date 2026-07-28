<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';
import CollectionPicker, { type CollectionChoice } from '../components/CollectionPicker.vue';
import { collectionPayload } from '../composables/lastCollection';

const props = defineProps<{
    batch: { id: number; label: string; uploadedAt: string; pendingCount: number };
    items: {
        id: number;
        thumbnailImageId: number | null;
        thumbnailVersion: string;
        title: string;
        status: string;
        reason: string | null;
        confidence: number | null;
        keyCard: boolean;
    }[];
    collections: { id: number; name: string }[];
}>();

const page = usePage<{ flash: { status: string | null } }>();

const collectionChoice = ref<CollectionChoice>({ collectionId: null, newName: '' });

function moveBatch(): void {
    if (!confirm(`Move all ${props.items.length} item(s) in this batch?`)) return;
    router.post(`/batches/${props.batch.id}/collection`, collectionPayload(collectionChoice.value), { preserveScroll: true });
}

// Reprocessing the whole batch asks which photos the AI should read,
// same as the per-item chooser.
const choosingSource = ref(false);

function runBatchReprocess(source: 'original' | 'cleaned'): void {
    choosingSource.value = false;
    router.post(`/batches/${props.batch.id}/reprocess`, { source }, { preserveScroll: true });
}

function deleteBatch(): void {
    if (!confirm(`Delete "${props.batch.label}" and its ${props.items.length} card(s)? This cannot be undone.`)) return;
    router.delete(`/batches/${props.batch.id}`, {
        onSuccess: () => router.visit('/batches'),
    });
}

// While any item is still processing, refresh so statuses and thumbnails
// come in without a manual reload.
let poll: number | null = null;

function stopPolling(): void {
    if (poll !== null) {
        clearInterval(poll);
        poll = null;
    }
}

watch(
    () => props.batch.pendingCount,
    (pending) => {
        if (pending > 0 && poll === null) {
            poll = window.setInterval(() => router.reload(), 4000);
        } else if (pending === 0) {
            stopPolling();
        }
    },
    { immediate: true },
);
onUnmounted(stopPolling);
</script>

<template>
    <Head :title="batch.label" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="min-w-0 truncate text-2xl font-bold text-gray-900">{{ batch.label }}</h1>
            <Link href="/batches" class="ml-2 shrink-0 text-sm font-semibold text-blue-600">All batches</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">Uploaded {{ batch.uploadedAt }} · {{ items.length }} item(s)</p>

        <p v-if="page.props.flash.status" class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">
            {{ page.props.flash.status }}
        </p>
        <p v-if="batch.pendingCount > 0" class="mt-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-700">
            ⏳ {{ batch.pendingCount }} item(s) still processing… this page updates by itself.
        </p>

        <div class="mt-4 flex flex-col gap-2">
            <button
                class="rounded-lg bg-gray-200 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300"
                @click="choosingSource = !choosingSource"
            >
                Reprocess Batch
            </button>
            <button
                class="rounded-lg bg-red-50 py-2 text-sm font-semibold text-red-600 hover:bg-red-100"
                @click="deleteBatch"
            >
                Delete Batch
            </button>
            <div v-if="choosingSource" class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-sm font-semibold text-gray-900">Which photos should the AI read?</p>
                <p class="mt-1 text-xs text-gray-500">Every card in this batch will be re-run; current details will be replaced.</p>
                <div class="mt-3 flex flex-col gap-2">
                    <button
                        class="rounded-lg bg-blue-600 px-3 py-2 text-left text-sm font-semibold text-white hover:bg-blue-700"
                        @click="runBatchReprocess('original')"
                    >
                        Original photos
                        <span class="block text-xs font-normal text-blue-100">Start fresh — photos go back to their original framing.</span>
                    </button>
                    <button
                        class="rounded-lg bg-blue-600 px-3 py-2 text-left text-sm font-semibold text-white hover:bg-blue-700"
                        @click="runBatchReprocess('cleaned')"
                    >
                        Cleaned photos
                        <span class="block text-xs font-normal text-blue-100">Keep adjustments — just re-read each card as shown.</span>
                    </button>
                    <button
                        class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100"
                        @click="choosingSource = false"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-gray-700">Move entire batch to collection</p>
            <div class="mt-2 flex flex-col gap-2">
                <CollectionPicker v-model="collectionChoice" :collections="collections" />
                <button
                    class="rounded-lg bg-blue-600 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                    @click="moveBatch"
                >
                    Move Batch
                </button>
            </div>
        </div>

        <div class="mt-4 flex flex-col gap-3">
            <Link
                v-for="item in items"
                :key="item.id"
                :href="`/items/${item.id}`"
                class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm"
            >
                <img
                    v-if="item.thumbnailImageId"
                    :src="`/thumbnails/${item.thumbnailImageId}?v=${item.thumbnailVersion}`"
                    :alt="item.title"
                    class="h-20 w-16 rounded-lg bg-gray-100 object-contain"
                />
                <div v-else class="h-20 w-16 rounded-lg bg-gray-200"></div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-gray-900"><span v-if="item.keyCard">⭐ </span>{{ item.title }}</p>
                    <p
                        class="text-sm"
                        :class="{
                            'text-green-700': item.status === 'Processed',
                            'text-amber-700': item.status === 'Needs Review',
                            'text-gray-500': item.status === 'Pending',
                        }"
                    >
                        {{ item.status }}<span v-if="item.reason"> · {{ item.reason }}</span>
                    </p>
                    <p v-if="item.confidence !== null" class="text-xs text-gray-400">{{ Math.round(item.confidence) }}% confidence</p>
                </div>
                <span class="text-sm font-semibold text-blue-600">View</span>
            </Link>
        </div>
    </div>
</template>
