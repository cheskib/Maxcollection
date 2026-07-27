<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import CollectionPicker, { type CollectionChoice } from '../components/CollectionPicker.vue';
import { collectionPayload, loadLastCollection, saveLastCollection } from '../composables/lastCollection';

const props = defineProps<{
    collections: { id: number; name: string }[];
}>();

const collectionChoice = ref<CollectionChoice>({ collectionId: null, newName: '' });

onMounted(() => {
    collectionChoice.value = loadLastCollection(props.collections);
});

const photosPerItem = ref<1 | 2>(2);
const pending = ref<File[]>([]);
const itemsCreated = ref(0);
// One batch per bulk session: the first item creates it, later items join it.
const batchId = ref<number | null>(null);
const uploading = ref(false);
const progress = ref<string | null>(null);
const error = ref<string | null>(null);

const cameraInput = ref<HTMLInputElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const pdfInput = ref<HTMLInputElement | null>(null);
const pdfNotice = ref<string | null>(null);

const pendingLabel = computed(() =>
    pending.value.length === 1 && photosPerItem.value === 2 ? '1 photo waiting for its pair' : null,
);

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function createItem(photos: File[]): Promise<void> {
    const body = new FormData();
    photos.forEach((photo) => body.append('photos[]', photo));
    if (batchId.value !== null) body.append('batch_id', String(batchId.value));
    Object.entries(collectionPayload(collectionChoice.value)).forEach(([key, value]) => body.append(key, value));

    const response = await fetch('/capture/bulk/items', {
        method: 'POST',
        headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json' },
        credentials: 'same-origin',
        body,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message ?? `Upload failed (${response.status})`);
    }

    if (typeof data.batchId === 'number') batchId.value = data.batchId;
    if (typeof data.collectionId === 'number') {
        collectionChoice.value = { collectionId: data.collectionId, newName: '' };
        saveLastCollection(data.collectionId);
    }
    itemsCreated.value += 1;
}

async function handleFiles(files: File[]): Promise<void> {
    error.value = null;
    pending.value.push(...files);

    uploading.value = true;
    try {
        while (pending.value.length >= photosPerItem.value) {
            const group = pending.value.splice(0, photosPerItem.value);
            progress.value = `Uploading item ${itemsCreated.value + 1}…`;
            await createItem(group);
        }
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Upload failed.';
    } finally {
        uploading.value = false;
        progress.value = null;
    }
}

function onInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';
    if (files.length) void handleFiles(files);
}

async function onPdf(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const pdf = input.files?.[0];
    input.value = '';
    if (!pdf) return;

    error.value = null;
    pdfNotice.value = null;
    uploading.value = true;
    progress.value = 'Uploading PDF…';

    try {
        const body = new FormData();
        body.append('pdf', pdf);
        body.append('photos_per_item', String(photosPerItem.value));
        Object.entries(collectionPayload(collectionChoice.value)).forEach(([key, value]) => body.append(key, value));
        if (typeof collectionChoice.value.collectionId === 'number') {
            saveLastCollection(collectionChoice.value.collectionId);
        }

        const response = await fetch('/capture/bulk/pdf', {
            method: 'POST',
            headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json' },
            credentials: 'same-origin',
            body,
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message ?? `Upload failed (${response.status})`);
        pdfNotice.value = data.message ?? 'PDF received.';
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'PDF upload failed.';
    } finally {
        uploading.value = false;
        progress.value = null;
    }
}

// An odd photo left over (e.g. a single-sided last card) becomes its own item.
async function finishPartial(): Promise<void> {
    if (!pending.value.length) return;
    uploading.value = true;
    try {
        await createItem(pending.value.splice(0));
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Upload failed.';
    } finally {
        uploading.value = false;
    }
}
</script>

<template>
    <Head title="Bulk Capture" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Bulk Capture</h1>
            <Link href="/capture" class="text-sm font-semibold text-blue-600">One at a time</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">
            Keep shooting — every {{ photosPerItem === 2 ? 'two photos become' : 'photo becomes' }} a new item automatically.
        </p>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-gray-700">Collection</p>
            <div class="mt-2">
                <CollectionPicker v-model="collectionChoice" :collections="collections" />
            </div>
        </div>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-gray-700">Photos per item</p>
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

        <div class="mt-5 rounded-xl bg-white p-5 text-center shadow-sm">
            <p class="text-4xl font-bold text-gray-900">{{ itemsCreated }}</p>
            <p class="mt-1 text-sm text-gray-500">item(s) created this session</p>
            <p v-if="pendingLabel" class="mt-2 text-sm text-amber-600">{{ pendingLabel }}</p>
            <p v-if="progress" class="mt-2 text-sm text-gray-500">{{ progress }}</p>
        </div>

        <p v-if="error" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <p v-if="pdfNotice" class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ pdfNotice }}</p>

        <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden" @change="onInput" />
        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="onInput" />
        <input ref="pdfInput" type="file" accept="application/pdf" class="hidden" @change="onPdf" />

        <div class="mt-6 flex flex-col gap-3">
            <button
                :disabled="uploading"
                class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                @click="cameraInput?.click()"
            >
                Take Picture
            </button>
            <button
                :disabled="uploading"
                class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                @click="fileInput?.click()"
            >
                Upload Pictures
            </button>
            <button
                :disabled="uploading"
                class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                @click="pdfInput?.click()"
            >
                Upload Scanner PDF
            </button>
            <button
                v-if="pending.length"
                :disabled="uploading"
                class="w-full rounded-lg bg-amber-500 py-3 font-semibold text-white hover:bg-amber-600 disabled:opacity-50"
                @click="finishPartial"
            >
                Finish item with {{ pending.length }} photo(s)
            </button>
            <Link href="/" class="w-full rounded-lg bg-green-600 py-3 text-center font-semibold text-white hover:bg-green-700">
                I'm Done
            </Link>
        </div>
    </div>
</template>
