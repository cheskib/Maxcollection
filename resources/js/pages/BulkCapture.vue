<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import CollectionPicker, { type CollectionChoice } from '../components/CollectionPicker.vue';
import { collectionPayload, loadLastCollection, saveLastCollection } from '../composables/lastCollection';

interface BatchStatus {
    id: number;
    label: string;
    converting: boolean;
    itemCount: number;
    captured: number;
    inProgress: number;
    processed: number;
    needsReview: number;
}

const props = defineProps<{
    collections: { id: number; name: string }[];
}>();

const collectionChoice = ref<CollectionChoice>({ collectionId: null, newName: '' });
const photosPerItem = ref<1 | 2>(2);
const batches = ref<BatchStatus[]>([]);
const uploading = ref(false);
const processing = ref(false);
const error = ref<string | null>(null);
const notice = ref<string | null>(null);
const pdfInput = ref<HTMLInputElement | null>(null);
let poller: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    collectionChoice.value = loadLastCollection(props.collections);
    poller = setInterval(refreshStatus, 4000);
});

onBeforeUnmount(() => {
    if (poller) clearInterval(poller);
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
                saveLastCollection(data.collectionId);
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
        <p class="mt-1 text-sm text-gray-500">Upload scanner PDFs — each becomes its own batch below.</p>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-gray-700">Collection</p>
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
        <button
            :disabled="uploading"
            class="mt-4 w-full rounded-xl bg-blue-600 py-4 font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-50"
            @click="pdfInput?.click()"
        >
            {{ uploading ? 'Uploading…' : '⬆ Add Batch PDF(s)' }}
        </button>

        <p v-if="error" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <p v-if="notice" class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ notice }}</p>

        <div v-if="batches.length" class="mt-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">This session's batches</p>
            <div class="mt-2 flex flex-col gap-3">
                <div v-for="batch in batches" :key="batch.id" class="rounded-xl bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="min-w-0 truncate font-semibold text-gray-900">{{ batch.label }}</p>
                        <Link :href="`/batches/${batch.id}`" class="ml-2 shrink-0 text-sm font-semibold text-blue-600">View</Link>
                    </div>
                    <p v-if="batch.converting" class="mt-1 text-sm text-gray-500">⏳ Converting PDF into items…</p>
                    <div v-else class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <span class="text-gray-700">{{ batch.itemCount }} item(s)</span>
                        <span v-if="batch.captured" class="text-blue-700">{{ batch.captured }} ready to process</span>
                        <span v-if="batch.inProgress" class="text-gray-500">{{ batch.inProgress }} processing…</span>
                        <span v-if="batch.processed" class="text-green-700">{{ batch.processed }} processed</span>
                        <span v-if="batch.needsReview" class="text-amber-700">{{ batch.needsReview }} needs review</span>
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

        <p v-else class="mt-8 text-center text-sm text-gray-400">No batches yet this session — add a PDF to begin.</p>
    </div>
</template>
