<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface CaptureImage {
    id: number;
    original_filename: string;
}

const props = defineProps<{
    item: { id: number; images: CaptureImage[] } | null;
}>();

const cameraInput = ref<HTMLInputElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const error = ref<string | null>(null);

function upload(event: Event): void {
    const input = event.target as HTMLInputElement;
    const photo = input.files?.[0];
    if (!photo) return;

    error.value = null;
    router.post(
        '/capture/images',
        { photo, item_id: props.item?.id ?? null },
        {
            forceFormData: true,
            onStart: () => (uploading.value = true),
            onFinish: () => {
                uploading.value = false;
                input.value = '';
            },
            onError: (errors) => (error.value = errors.photo ?? Object.values(errors)[0] ?? 'Upload failed.'),
        },
    );
}

function deleteImage(image: CaptureImage): void {
    if (!confirm('Delete this picture?')) return;
    router.delete(`/images/${image.id}`);
}
</script>

<template>
    <Head title="Capture Item" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Capture Item</h1>
            <Link v-if="!item" href="/capture/bulk" class="text-sm font-semibold text-blue-600">Bulk mode</Link>
        </div>
        <p v-if="item" class="mt-1 text-center text-sm text-gray-500">Item #{{ item.id }} · {{ item.images.length }} picture(s)</p>
        <p v-else class="mt-1 text-center text-sm text-gray-500">The first picture creates the item.</p>

        <!-- Hidden inputs: one opens the camera on mobile, the other the file picker -->
        <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden" @change="upload" />
        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="upload" />

        <div v-if="item" class="mt-6 grid grid-cols-2 gap-3">
            <div v-for="image in item.images" :key="image.id" class="relative overflow-hidden rounded-xl bg-white shadow-sm">
                <img :src="`/images/${image.id}`" :alt="image.original_filename" class="h-40 w-full object-cover" />
                <button
                    class="absolute right-2 top-2 rounded-lg bg-red-600 px-2 py-1 text-xs font-semibold text-white hover:bg-red-700"
                    @click="deleteImage(image)"
                >
                    Delete
                </button>
            </div>
        </div>

        <p v-if="error" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <p v-if="uploading" class="mt-4 text-center text-sm text-gray-500">Uploading…</p>

        <div class="mt-6 flex flex-col gap-3">
            <button
                :disabled="uploading"
                class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                @click="cameraInput?.click()"
            >
                {{ item ? 'Take Another Picture' : 'Take Picture' }}
            </button>
            <button
                :disabled="uploading"
                class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                @click="fileInput?.click()"
            >
                Upload Picture
            </button>
            <Link
                href="/"
                class="w-full rounded-lg bg-green-600 py-3 text-center font-semibold text-white hover:bg-green-700"
            >
                I'm Done
            </Link>
        </div>
    </div>
</template>
