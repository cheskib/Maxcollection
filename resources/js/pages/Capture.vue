<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CameraCapture from '../components/CameraCapture.vue';
import CollectionPicker, { type CollectionChoice } from '../components/CollectionPicker.vue';
import { collectionPayload } from '../composables/lastCollection';

interface CaptureImage {
    id: number;
    original_filename: string;
    version: string;
    role?: string | null;
}

const props = defineProps<{
    item: { id: number; images: CaptureImage[] } | null;
    collections: { id: number; name: string }[];
}>();

type Step = 'front' | 'back' | 'extra' | 'autograph';

// The wizard resumes from where the photos left off when the page reloads
// after each upload.
const step = computed<Step>(() => {
    if (finishing.value) return 'autograph';
    const images = props.item?.images ?? [];
    if (images.length === 0) return 'front';
    if (!images.some((image) => image.role === 'back') && images.length === 1) return 'back';
    return 'extra';
});

const STEP_COPY: Record<Step, { title: string; hint: string }> = {
    front: { title: 'Take the FRONT picture', hint: 'Hold the card front toward the camera.' },
    back: { title: 'Now take the BACK picture', hint: 'Flip the card over.' },
    extra: { title: 'Any additional pictures?', hint: 'Close-ups of corners, autographs, or details — optional.' },
    autograph: { title: 'One last question', hint: '' },
};

const collectionChoice = ref<CollectionChoice>({ collectionId: null, newName: '' });
// Choosing the collection is the wizard's first question; the camera
// only appears once it's answered.
const collectionConfirmed = ref(false);
const collectionChosen = computed(() => {
    const choice = collectionChoice.value;
    return typeof choice.collectionId === 'number' || (choice.collectionId === 'new' && choice.newName.trim() !== '');
});
const uploading = ref(false);
const finishing = ref(false);
const hasAutograph = ref(false);
const error = ref<string | null>(null);

const cameraInput = ref<HTMLInputElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

// No last-used default here: the collection must be chosen consciously
// every session so cards never land in the wrong one unnoticed.

function roleForStep(): string {
    if (step.value === 'front') return 'front';
    if (step.value === 'back') return 'back';
    return 'detail';
}

// The in-page camera is the main path; if the browser refuses camera
// access we fall back to the classic take/upload buttons.
const cameraUnsupported = ref(false);

const frontThumb = computed(() => {
    const image = props.item?.images.find((entry) => entry.role === 'front') ?? props.item?.images[0];
    return image ? `/thumbnails/${image.id}?v=${image.version}` : null;
});
const backThumb = computed(() => {
    const image = props.item?.images.find((entry) => entry.role === 'back');
    return image ? `/thumbnails/${image.id}?v=${image.version}` : null;
});
const detailCount = computed(() => props.item?.images.filter((entry) => entry.role === 'detail').length ?? 0);

function postPhoto(photo: File): void {
    error.value = null;

    router.post(
        '/capture/images',
        {
            photo,
            item_id: props.item?.id ?? null,
            role: roleForStep(),
            ...collectionPayload(collectionChoice.value),
        },
        {
            forceFormData: true,
            // Keep the page component (and the live camera) alive across
            // the upload round-trip.
            preserveState: true,
            onStart: () => (uploading.value = true),
            onFinish: () => (uploading.value = false),
            onError: (errors) => (error.value = errors.photo ?? Object.values(errors)[0] ?? 'Upload failed.'),
        },
    );
}

function upload(event: Event): void {
    const input = event.target as HTMLInputElement;
    const photo = input.files?.[0];
    input.value = '';
    if (!photo) return;

    postPhoto(photo);
}

function deleteImage(image: CaptureImage): void {
    if (!confirm('Delete this picture?')) return;
    router.delete(`/images/${image.id}`);
}

function finish(): void {
    if (!props.item) return;
    router.post(`/items/${props.item.id}/autograph`, { authentic: hasAutograph.value });
}

const ROLE_LABELS: Record<string, string> = { front: 'Front', back: 'Back', detail: 'Detail' };
</script>

<template>
    <Head title="Capture Item" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Capture Item</h1>
            <Link href="/capture/bulk" class="text-sm font-semibold text-blue-600">Bulk mode</Link>
        </div>

        <div v-if="!item" class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-gray-700">
                {{ collectionConfirmed ? 'Collection' : 'First — which collection does this go in?' }}
            </p>
            <div class="mt-2">
                <CollectionPicker v-model="collectionChoice" :collections="collections" />
            </div>
            <button
                v-if="!collectionConfirmed"
                :disabled="!collectionChosen"
                class="mt-3 w-full rounded-lg bg-blue-600 py-2.5 font-semibold text-white hover:bg-blue-700 disabled:bg-gray-300"
                @click="collectionConfirmed = true"
            >
                Continue →
            </button>
        </div>

        <template v-if="item || collectionConfirmed">
        <CameraCapture
            v-if="step !== 'autograph' && !cameraUnsupported"
            :step="step"
            :front-thumb="frontThumb"
            :back-thumb="backThumb"
            :detail-count="detailCount"
            :can-finish="Boolean(item)"
            :uploading="uploading"
            @photo="postPhoto"
            @gallery="fileInput?.click()"
            @finish="finishing = true"
            @exit="router.visit('/')"
            @unsupported="cameraUnsupported = true"
        />

        <div class="mt-4 rounded-xl bg-blue-50 p-4 text-center">
            <p class="font-semibold text-blue-900">{{ STEP_COPY[step].title }}</p>
            <p v-if="STEP_COPY[step].hint" class="mt-1 text-sm text-blue-700">{{ STEP_COPY[step].hint }}</p>
        </div>

        <div v-if="item?.images.length" class="mt-4 grid grid-cols-3 gap-2">
            <div v-for="image in item.images" :key="image.id" class="relative">
                <img :src="`/thumbnails/${image.id}?v=${image.version}`" :alt="image.original_filename" class="h-28 w-full rounded-lg bg-gray-100 object-contain" />
                <span class="absolute left-1 top-1 rounded bg-gray-900/70 px-1.5 py-0.5 text-xs font-semibold text-white">
                    {{ ROLE_LABELS[image.role ?? ''] ?? 'Photo' }}
                </span>
                <button
                    class="absolute right-1 top-1 rounded bg-red-600 px-1.5 py-0.5 text-xs font-semibold text-white"
                    @click="deleteImage(image)"
                >
                    ✕
                </button>
            </div>
        </div>

        <p v-if="error" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <p v-if="uploading" class="mt-4 text-center text-sm text-gray-500">Uploading…</p>

        <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden" @change="upload" />
        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="upload" />

        <div v-if="step !== 'autograph'" class="mt-5 flex flex-col gap-3">
            <button
                :disabled="uploading"
                class="w-full rounded-xl bg-blue-600 py-4 font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-50"
                @click="cameraInput?.click()"
            >
                📷 {{ step === 'front' ? 'Take Front Picture' : step === 'back' ? 'Take Back Picture' : 'Take Another Picture' }}
            </button>
            <button
                :disabled="uploading"
                class="w-full rounded-lg bg-gray-100 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200 disabled:opacity-50"
                @click="fileInput?.click()"
            >
                …or upload from gallery
            </button>
            <button
                v-if="item"
                :disabled="uploading"
                class="w-full rounded-lg bg-green-600 py-3 font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                @click="finishing = true"
            >
                ✓ That's all the pictures
            </button>
        </div>

        <div v-else class="mt-5 flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm">
            <label class="flex items-center gap-3">
                <input v-model="hasAutograph" type="checkbox" class="h-5 w-5 rounded border-gray-300" />
                <span class="text-gray-900">This item has an <strong>authentic autograph</strong></span>
            </label>
            <p class="text-sm text-gray-500">Leave unchecked if there's no autograph (the usual case).</p>
            <button
                class="w-full rounded-xl bg-green-600 py-4 font-semibold text-white shadow-sm hover:bg-green-700"
                @click="finish"
            >
                ✓ Finish — Save Item
            </button>
            <button class="text-sm font-semibold text-gray-500 hover:text-gray-700" @click="finishing = false">
                ← Back to pictures
            </button>
        </div>
        </template>
    </div>
</template>
