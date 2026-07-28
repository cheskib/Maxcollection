<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';
import CollectionPicker, { type CollectionChoice } from '../components/CollectionPicker.vue';

interface Field {
    name: string;
    label: string;
    value: string | null;
}

interface LogLine {
    level: string;
    message: string;
    at: string | null;
}

const props = defineProps<{
    collections: { id: number; name: string }[];
    item: {
        id: number;
        collectionId: number | null;
        status: string;
        reviewReason: string | null;
        title: string;
        category: string;
        confidence: number | null;
        processedAt: string | null;
        images: { id: number; original_filename: string; version: string; adjusted: boolean; canUndo: boolean }[];
        fields: Field[];
        value: {
            ours: { from: number | null; to: number | null };
            ai: { from: number | null; to: number | null };
            market: { value: number | null; match: string | null; checkedAt: string | null };
        };
        keyCard: boolean;
        copies: { count: number; others: number[] };
        processing: { status: string; model: string | null; error: string | null; finishedAt: string | null; logs: LogLine[] } | null;
    };
}>();

const page = usePage<{ flash: { status: string | null } }>();

// Reprocessing always asks which photos the AI should read.
const pendingTier = ref<'standard' | 'premium' | null>(null);

function reprocess(tier: 'standard' | 'premium'): void {
    pendingTier.value = pendingTier.value === tier ? null : tier;
}

function runReprocess(source: 'original' | 'cleaned'): void {
    if (pendingTier.value === null) return;
    router.post(
        `/items/${props.item.id}/reprocess`,
        { tier: pendingTier.value, source },
        { preserveScroll: true },
    );
    pendingTier.value = null;
}

// While the AI is working, refresh the item every few seconds so the
// photos and metadata appear without a manual page reload.
let poll: number | null = null;

function stopPolling(): void {
    if (poll !== null) {
        clearInterval(poll);
        poll = null;
    }
}

watch(
    () => props.item.status,
    (status) => {
        const busy = status === 'queued' || status === 'processing';
        if (busy && poll === null) {
            poll = window.setInterval(() => router.reload({ only: ['item'] }), 4000);
        } else if (!busy) {
            stopPolling();
        }
    },
    { immediate: true },
);
onUnmounted(stopPolling);

function rotate(imageId: number): void {
    router.post(`/images/${imageId}/rotate`, {}, { preserveScroll: true });
}

// Swap back to the cleanup that was in place before the last AI pass.
function undoCleanup(imageId: number): void {
    router.post(`/images/${imageId}/undo`, {}, { preserveScroll: true });
}

const collectionChoice = ref<CollectionChoice>({ collectionId: props.item.collectionId, newName: '' });

const addCameraInput = ref<HTMLInputElement | null>(null);
const addFileInput = ref<HTMLInputElement | null>(null);
const addingPhoto = ref(false);

// Add a forgotten back or a detail shot directly from the item's page.
function onAddPhoto(event: Event): void {
    const input = event.target as HTMLInputElement;
    const photo = input.files?.[0];
    input.value = '';
    if (!photo) return;

    router.post(
        '/capture/images',
        { photo, item_id: props.item.id, stay: 1 },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => (addingPhoto.value = true),
            onFinish: () => (addingPhoto.value = false),
        },
    );
}

function saveCollection(): void {
    router.put(
        `/items/${props.item.id}/collection`,
        {
            collection_id: collectionChoice.value.collectionId === 'new' ? null : collectionChoice.value.collectionId,
            new_collection_name: collectionChoice.value.collectionId === 'new' ? collectionChoice.value.newName : '',
        },
        { preserveScroll: true },
    );
}

function deleteItem(): void {
    if (!confirm('Delete this item? Its photographs, details, and history will be permanently removed.')) return;
    router.delete(`/items/${props.item.id}`);
}

function money(from: number | null, to: number | null): string {
    const fmt = (v: number) => `$${v.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
    if (from !== null && to !== null) return from === to ? fmt(from) : `${fmt(from)} – ${fmt(to)}`;
    return fmt((from ?? to) as number);
}

function back(): void {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/items');
    }
}
</script>

<template>
    <Head :title="item.title" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="min-w-0 truncate text-xl font-bold text-gray-900">{{ item.title }}</h1>
            <button class="ml-3 shrink-0 text-sm font-semibold text-blue-600" @click="back">Back</button>
        </div>
        <p class="mt-1 text-sm text-gray-500">
            {{ item.category }}
            <span v-if="item.confidence !== null"> · {{ Math.round(item.confidence) }}% confidence</span>
        </p>

        <p v-if="page.props.flash.status" class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">
            {{ page.props.flash.status }}
        </p>
        <p v-if="item.reviewReason" class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
            Needs review: {{ item.reviewReason }}
        </p>
        <p v-if="item.keyCard" class="mt-3 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm font-semibold text-yellow-800">
            ⭐ Possible key card — this player is on your key names watchlist. Worth a closer look.
        </p>
        <p v-if="item.copies.count > 1" class="mt-3 rounded-lg bg-indigo-50 p-3 text-sm text-indigo-800">
            📇 You own <strong>×{{ item.copies.count }}</strong> of this card —
            <template v-for="(copyId, index) in item.copies.others" :key="copyId">
                <Link :href="`/items/${copyId}`" class="font-semibold underline">copy {{ index + 2 }}</Link><span v-if="index < item.copies.others.length - 1">, </span>
            </template>
        </p>
        <p
            v-if="item.status === 'queued' || item.status === 'processing'"
            class="mt-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-700"
        >
            ⏳ The AI is reading this item… the page updates by itself when it finishes.
        </p>

<!-- Adjusted photos show original vs cleaned side by side; the full
     photograph is always visible, never cropped in display -->
        <div class="mt-4 flex flex-col gap-3">
            <div v-for="image in item.images" :key="image.id">
                <div v-if="image.adjusted" class="grid grid-cols-2 gap-2">
                    <div>
                        <a :href="`/images/${image.id}?original=1`" target="_blank">
                            <img
                                :src="`/thumbnails/${image.id}?original=1&v=${image.version}`"
                                :alt="`${image.original_filename} (original)`"
                                class="w-full rounded-xl bg-gray-100 object-contain shadow-sm"
                            />
                        </a>
                        <p class="mt-1 text-center text-xs text-gray-400">Original</p>
                    </div>
                    <div>
                        <div class="relative">
                            <a :href="`/images/${image.id}`" target="_blank">
                                <img :src="`/thumbnails/${image.id}?v=${image.version}`" :alt="image.original_filename" class="w-full rounded-xl bg-gray-100 object-contain shadow-sm" />
                            </a>
                            <button
                                class="absolute right-2 top-2 rounded-lg bg-gray-900/70 px-2 py-1 text-sm font-semibold text-white hover:bg-gray-900"
                                title="Rotate a quarter turn"
                                @click="rotate(image.id)"
                            >
                                ↻
                            </button>
                            <Link
                                :href="`/images/${image.id}/trim`"
                                class="absolute left-2 top-2 rounded-lg bg-gray-900/70 px-2 py-1 text-sm font-semibold text-white hover:bg-gray-900"
                                title="Trim edges"
                            >
                                ✂
                            </Link>
                            <button
                                v-if="image.canUndo"
                                class="absolute bottom-2 right-2 rounded-lg bg-gray-900/70 px-2 py-1 text-sm font-semibold text-white hover:bg-gray-900"
                                title="Undo the last AI cleanup"
                                @click="undoCleanup(image.id)"
                            >
                                ⎌
                            </button>
                        </div>
                        <p class="mt-1 text-center text-xs text-gray-400">Cleaned</p>
                    </div>
                </div>
                <div v-else class="relative">
                    <a :href="`/images/${image.id}`" target="_blank">
                        <img :src="`/thumbnails/${image.id}?v=${image.version}`" :alt="image.original_filename" class="w-full rounded-xl bg-gray-100 object-contain shadow-sm" />
                    </a>
                    <button
                        class="absolute right-2 top-2 rounded-lg bg-gray-900/70 px-2 py-1 text-sm font-semibold text-white hover:bg-gray-900"
                        title="Rotate a quarter turn"
                        @click="rotate(image.id)"
                    >
                        ↻
                    </button>
                    <Link
                        :href="`/images/${image.id}/trim`"
                        class="absolute left-2 top-2 rounded-lg bg-gray-900/70 px-2 py-1 text-sm font-semibold text-white hover:bg-gray-900"
                        title="Trim edges"
                    >
                        ✂
                    </Link>
                    <button
                        v-if="image.canUndo"
                        class="absolute bottom-2 right-2 rounded-lg bg-gray-900/70 px-2 py-1 text-sm font-semibold text-white hover:bg-gray-900"
                        title="Undo the last AI cleanup"
                        @click="undoCleanup(image.id)"
                    >
                        ⎌
                    </button>
                </div>
            </div>
        </div>

        <input ref="addCameraInput" type="file" accept="image/*" capture="environment" class="hidden" @change="onAddPhoto" />
        <input ref="addFileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onAddPhoto" />
        <div class="mt-3 flex gap-2">
            <button
                :disabled="addingPhoto"
                class="flex-1 rounded-lg bg-gray-100 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 disabled:opacity-50"
                @click="addCameraInput?.click()"
            >
                📷 Add Photo
            </button>
            <button
                :disabled="addingPhoto"
                class="flex-1 rounded-lg bg-gray-100 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 disabled:opacity-50"
                @click="addFileInput?.click()"
            >
                🖼 Upload Photo
            </button>
        </div>
        <p v-if="addingPhoto" class="mt-2 text-center text-sm text-gray-500">Uploading…</p>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">Collection</h2>
            <div class="mt-2 flex flex-col gap-2">
                <CollectionPicker v-model="collectionChoice" :collections="collections" />
                <button
                    class="rounded-lg bg-blue-600 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                    @click="saveCollection"
                >
                    Save Collection
                </button>
            </div>
        </div>

        <div class="mt-6 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">Value</h2>
            <dl class="mt-2 divide-y divide-gray-100">
                <div v-if="item.value.ours.from !== null || item.value.ours.to !== null" class="flex justify-between gap-3 py-2 text-sm">
                    <dt class="text-gray-500">Our Value</dt>
                    <dd class="text-right font-medium text-gray-900">{{ money(item.value.ours.from, item.value.ours.to) }}</dd>
                </div>
                <div v-if="item.value.ai.from !== null || item.value.ai.to !== null" class="flex justify-between gap-3 py-2 text-sm">
                    <dt class="text-gray-500">AI Ballpark</dt>
                    <dd class="text-right font-medium text-gray-900">{{ money(item.value.ai.from, item.value.ai.to) }}</dd>
                </div>
                <div v-if="item.value.market.value !== null" class="flex justify-between gap-3 py-2 text-sm">
                    <dt class="text-gray-500">
                        Market
                        <span v-if="item.value.market.checkedAt" class="block text-xs text-gray-400">as of {{ item.value.market.checkedAt }}</span>
                    </dt>
                    <dd class="text-right font-medium text-gray-900">
                        {{ money(item.value.market.value, item.value.market.value) }}
                        <span v-if="item.value.market.match" class="block text-xs font-normal text-gray-400">{{ item.value.market.match }}</span>
                    </dd>
                </div>
            </dl>
            <button
                class="mt-2 w-full rounded-lg bg-gray-100 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200"
                @click="router.post(`/items/${item.id}/market-value`, {}, { preserveScroll: true })"
            >
                💹 {{ item.value.market.value !== null ? 'Refresh Market Price' : 'Get Market Price' }}
            </button>
        </div>

        <div v-if="item.fields.length" class="mt-6 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">Metadata</h2>
            <dl class="mt-2 divide-y divide-gray-100">
                <div v-for="field in item.fields" :key="field.name" class="flex justify-between gap-3 py-2 text-sm">
                    <dt class="text-gray-500">{{ field.label }}</dt>
                    <dd class="text-right font-medium text-gray-900">{{ field.value ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div v-if="item.processing" class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">Processing</h2>
            <p class="mt-1 text-sm text-gray-500">
                Status: {{ item.processing.status }}
                <span v-if="item.processing.model"> · {{ item.processing.model }}</span>
                <span v-if="item.processing.finishedAt"> · {{ item.processing.finishedAt }}</span>
            </p>
            <p v-if="item.processing.error" class="mt-1 text-sm text-red-600">{{ item.processing.error }}</p>
            <ul class="mt-2 space-y-1 text-xs text-gray-400">
                <li v-for="(log, index) in item.processing.logs" :key="index" :class="{ 'text-red-500': log.level === 'error' }">
                    {{ log.at }} — {{ log.message }}
                </li>
            </ul>
        </div>

        <div class="mt-6 flex flex-col gap-3">
            <Link
                :href="`/items/${item.id}/edit`"
                class="w-full rounded-lg bg-blue-600 py-3 text-center font-semibold text-white hover:bg-blue-700"
            >
                Edit
            </Link>
            <button
                class="w-full rounded-lg bg-gray-200 py-3 font-semibold text-gray-700 hover:bg-gray-300"
                @click="reprocess('standard')"
            >
                Reprocess (Standard)
            </button>
            <button
                class="w-full rounded-lg bg-purple-600 py-3 font-semibold text-white hover:bg-purple-700"
                @click="reprocess('premium')"
            >
                ★ Premium Analysis
            </button>
            <div v-if="pendingTier" class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-sm font-semibold text-gray-900">Which photos should the AI read?</p>
                <p class="mt-1 text-xs text-gray-500">Current details will be replaced with what it finds.</p>
                <div class="mt-3 flex flex-col gap-2">
                    <button
                        class="rounded-lg bg-blue-600 px-3 py-2 text-left text-sm font-semibold text-white hover:bg-blue-700"
                        @click="runReprocess('original')"
                    >
                        Original photos
                        <span class="block text-xs font-normal text-blue-100">Start fresh — photos go back to their original framing.</span>
                    </button>
                    <button
                        class="rounded-lg bg-blue-600 px-3 py-2 text-left text-sm font-semibold text-white hover:bg-blue-700"
                        @click="runReprocess('cleaned')"
                    >
                        Cleaned photos
                        <span class="block text-xs font-normal text-blue-100">Keep my adjustments — just re-read the item as shown.</span>
                    </button>
                    <button
                        class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100"
                        @click="pendingTier = null"
                    >
                        Cancel
                    </button>
                </div>
            </div>
            <button
                class="w-full rounded-lg bg-red-600 py-3 font-semibold text-white hover:bg-red-700"
                @click="deleteItem"
            >
                Delete Item
            </button>
        </div>
    </div>
</template>
