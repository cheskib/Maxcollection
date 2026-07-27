<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    totals: { photos: number; items: number; perItem: number };
    bySide: { front: number; back: number; detail: number; unlabeled: number };
    singlePhotoItems: { id: number; title: string; thumbnailImageId: number | null; thumbnailVersion: string }[];
    bySource: { scanner: number; phone: number };
    storageMb: number;
}>();
</script>

<template>
    <Head title="Photos" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Photos</h1>
            <Link href="/" class="ml-2 shrink-0 text-sm font-semibold text-blue-600">Home</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">
            {{ totals.photos }} photo(s) across {{ totals.items }} item(s) — about {{ totals.perItem }} per item.
        </p>

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">By side</p>
        <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
            <div class="flex items-center justify-between p-4">
                <p class="text-sm text-gray-700">Fronts</p>
                <span class="font-bold text-gray-900">{{ bySide.front }}</span>
            </div>
            <div class="flex items-center justify-between p-4">
                <p class="text-sm text-gray-700">Backs</p>
                <span class="font-bold text-gray-900">{{ bySide.back }}</span>
            </div>
            <div class="flex items-center justify-between p-4">
                <p class="text-sm text-gray-700">Detail shots</p>
                <span class="font-bold text-gray-900">{{ bySide.detail }}</span>
            </div>
            <div v-if="bySide.unlabeled > 0" class="flex items-center justify-between p-4">
                <p class="text-sm text-gray-500">Not yet labeled</p>
                <span class="font-bold text-gray-900">{{ bySide.unlabeled }}</span>
            </div>
        </div>

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">
            ⚠️ Cards with only one photo ({{ singlePhotoItems.length }})
        </p>
        <p v-if="singlePhotoItems.length === 0" class="mt-2 rounded-xl bg-white p-4 text-sm text-gray-500 shadow-sm">
            Every card has more than one photo. 🎉
        </p>
        <div v-else class="mt-2 flex flex-col gap-2">
            <Link
                v-for="item in singlePhotoItems"
                :key="item.id"
                :href="`/items/${item.id}`"
                class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm hover:bg-gray-50"
            >
                <img
                    v-if="item.thumbnailImageId"
                    :src="`/thumbnails/${item.thumbnailImageId}?v=${item.thumbnailVersion}`"
                    :alt="item.title"
                    class="h-16 w-12 rounded-lg bg-gray-100 object-contain"
                />
                <div v-else class="h-16 w-12 rounded-lg bg-gray-200"></div>
                <p class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-900">{{ item.title }}</p>
                <span class="shrink-0 text-xs font-semibold text-blue-600">Add photo ›</span>
            </Link>
        </div>

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">By source</p>
        <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
            <div class="flex items-center justify-between p-4">
                <p class="text-sm text-gray-700">Scanner PDFs</p>
                <span class="font-bold text-gray-900">{{ bySource.scanner }}</span>
            </div>
            <div class="flex items-center justify-between p-4">
                <p class="text-sm text-gray-700">Phone photos</p>
                <span class="font-bold text-gray-900">{{ bySource.phone }}</span>
            </div>
        </div>

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Storage</p>
        <div class="mt-2 rounded-xl bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-700">Original photos on disk: <span class="font-bold text-gray-900">{{ storageMb }} MB</span></p>
        </div>
    </div>
</template>
