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
            purchase: number | null;
            insurance: number | null;
            ai: { from: number | null; to: number | null };
            check: { label: string; url: string }[];
            market: { value: number | null; match: string | null; checkedAt: string | null };
        };
        keyCard: boolean;
        disposition: string | null;
        withdrawal: {
            reason: string;
            reasonLabel: string;
            notes: string | null;
            salePrice: number | null;
            saleDate: string | null;
            buyer: string | null;
            platform: string | null;
            destination: string | null;
            by: string;
            at: string;
        } | null;
        withdrawalHistory: {
            id: number;
            reasonLabel: string;
            destination: string | null;
            notes: string | null;
            by: string;
            at: string;
            reinstatedAt: string | null;
            reinstateNotes: string | null;
        }[];
        copies: { count: number; others: number[] };
        processing: { status: string; model: string | null; error: string | null; finishedAt: string | null; logs: LogLine[] } | null;
    };
}>();

const page = usePage<{ flash: { status: string | null }; auth: { isAdmin: boolean } }>();

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

// Documented removal from the collection — admin only. The record stays
// forever; only the disposition changes.
const REMOVAL_REASONS = [
    { value: 'sold', label: 'Sold' },
    { value: 'moved', label: 'Moved to safer storage' },
    { value: 'grading', label: 'Sent for grading' },
    { value: 'damaged', label: 'Damaged' },
    { value: 'lost', label: 'Lost' },
    { value: 'gift', label: 'Gift' },
    { value: 'other', label: 'Other' },
];

const removing = ref(false);
const removal = ref({ reason: 'sold', notes: '', sale_price: '', sale_date: '', buyer: '', platform: '', destination: '' });
const showRemovalHistory = ref(false);

function submitRemoval(): void {
    router.post(`/items/${props.item.id}/withdraw`, removal.value, {
        preserveScroll: true,
        onSuccess: () => (removing.value = false),
    });
}

function reinstate(): void {
    const notes = prompt('Reinstate this card — where is it going, and any notes? (e.g. "back in original bag" or "returned from grading, PSA 9")');
    if (notes === null) return;
    router.post(`/items/${props.item.id}/reinstate`, { notes }, { preserveScroll: true });
}

function updateLocation(): void {
    const destination = prompt('New location for this card:');
    if (!destination) return;
    router.post(`/items/${props.item.id}/location`, { destination }, { preserveScroll: true });
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

<!-- Disposition: what this card IS right now -->
        <div
            v-if="item.withdrawal"
            class="mt-3 rounded-lg border p-3 text-sm"
            :class="item.disposition === 'gone' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-300 bg-amber-50 text-amber-800'"
        >
            <p class="font-bold">
                {{ item.disposition === 'gone' ? '🔴' : '🟡' }} {{ item.withdrawal.reasonLabel }}
                <template v-if="item.withdrawal.salePrice !== null"> — ${{ item.withdrawal.salePrice.toLocaleString() }}</template>
                <template v-if="item.withdrawal.saleDate"> · {{ item.withdrawal.saleDate }}</template>
            </p>
            <p class="mt-0.5">
                <template v-if="item.withdrawal.buyer">Buyer: {{ item.withdrawal.buyer }}<template v-if="item.withdrawal.platform"> ({{ item.withdrawal.platform }})</template> · </template>
                <template v-else-if="item.withdrawal.platform">{{ item.withdrawal.platform }} · </template>
                <template v-if="item.withdrawal.destination">Now at: {{ item.withdrawal.destination }} · </template>
                By {{ item.withdrawal.by }}, {{ item.withdrawal.at }}
            </p>
            <p v-if="item.withdrawal.notes" class="mt-0.5">{{ item.withdrawal.notes }}</p>
            <div v-if="page.props.auth.isAdmin" class="mt-2 flex gap-3">
                <button class="text-xs font-semibold underline" @click="reinstate">⎌ Reinstate</button>
                <button v-if="item.disposition === 'relocated'" class="text-xs font-semibold underline" @click="updateLocation">
                    📍 Update location
                </button>
            </div>
        </div>
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
                <div v-if="item.value.purchase !== null" class="flex justify-between gap-3 py-2 text-sm">
                    <dt class="text-gray-500">Purchase Price</dt>
                    <dd class="text-right font-medium text-gray-900">{{ money(item.value.purchase, item.value.purchase) }}</dd>
                </div>
                <div v-if="item.value.insurance !== null" class="flex justify-between gap-3 py-2 text-sm">
                    <dt class="text-gray-500">Insurance Value</dt>
                    <dd class="text-right font-medium text-gray-900">{{ money(item.value.insurance, item.value.insurance) }}</dd>
                </div>
                <div v-if="item.value.ai.from !== null || item.value.ai.to !== null" class="flex justify-between gap-3 py-2 text-sm">
                    <dt class="text-gray-500">
                        AI Ballpark
                        <!-- The ballpark is the AI's judgment, not a lookup — these
                             links are how to audit it against the real market. -->
                        <span v-if="item.value.check.length" class="block text-xs font-normal">
                            Check:
                            <template v-for="(link, index) in item.value.check" :key="link.url">
                                <a :href="link.url" target="_blank" rel="noopener" class="font-semibold text-blue-600 hover:underline">{{ link.label }} ↗</a>
                                <span v-if="index < item.value.check.length - 1"> · </span>
                            </template>
                        </span>
                    </dt>
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
            <template v-if="page.props.auth.isAdmin && !item.disposition">
                <button
                    v-if="!removing"
                    class="w-full rounded-lg bg-amber-100 py-3 font-semibold text-amber-800 hover:bg-amber-200"
                    @click="removing = true"
                >
                    📤 Remove from Collection…
                </button>
                <div v-else class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-gray-900">Remove this card — documented</p>
                    <select v-model="removal.reason" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                        <option v-for="reason in REMOVAL_REASONS" :key="reason.value" :value="reason.value">{{ reason.label }}</option>
                    </select>
                    <template v-if="removal.reason === 'sold'">
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <input v-model="removal.sale_price" type="number" step="0.01" min="0" placeholder="Sale price $" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                            <input v-model="removal.sale_date" type="date" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <input v-model="removal.buyer" type="text" placeholder="Buyer" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                            <input v-model="removal.platform" type="text" placeholder="Platform (eBay…)" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                        </div>
                    </template>
                    <input
                        v-else-if="removal.reason === 'moved' || removal.reason === 'grading'"
                        v-model="removal.destination"
                        type="text"
                        placeholder="Destination (home safe, PSA…)"
                        class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                    />
                    <textarea
                        v-model="removal.notes"
                        rows="2"
                        :placeholder="removal.reason === 'other' ? 'Notes (required)' : 'Notes (optional)'"
                        class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                    ></textarea>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <button class="rounded-lg bg-amber-600 py-2.5 font-semibold text-white hover:bg-amber-700" @click="submitRemoval">
                            Record Removal
                        </button>
                        <button class="rounded-lg bg-white py-2.5 font-semibold text-gray-600 hover:bg-gray-100" @click="removing = false">
                            Cancel
                        </button>
                    </div>
                </div>
            </template>

            <template v-if="item.withdrawalHistory.length">
                <button class="text-left text-xs font-semibold text-gray-400 hover:text-gray-600" @click="showRemovalHistory = !showRemovalHistory">
                    {{ showRemovalHistory ? '▾' : '▸' }} Removal history ({{ item.withdrawalHistory.length }})
                </button>
                <div v-if="showRemovalHistory" class="divide-y divide-gray-100 rounded-xl bg-white p-3 text-xs text-gray-600 shadow-sm">
                    <div v-for="entry in item.withdrawalHistory" :key="entry.id" class="py-2">
                        <p class="font-semibold text-gray-800">
                            {{ entry.reasonLabel }}<template v-if="entry.destination"> → {{ entry.destination }}</template>
                            · {{ entry.at }} by {{ entry.by }}
                        </p>
                        <p v-if="entry.notes">{{ entry.notes }}</p>
                        <p v-if="entry.reinstatedAt" class="text-green-700">
                            ⎌ Reinstated {{ entry.reinstatedAt }}<template v-if="entry.reinstateNotes"> — {{ entry.reinstateNotes }}</template>
                        </p>
                    </div>
                </div>
            </template>

            <button
                v-if="page.props.auth.isAdmin"
                class="w-full rounded-lg bg-red-600 py-3 font-semibold text-white hover:bg-red-700"
                @click="deleteItem"
            >
                Delete Item
            </button>
        </div>
    </div>
</template>
