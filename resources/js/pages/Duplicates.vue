<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    groups: {
        title: string;
        copies: number;
        itemId: number;
        thumbnailImageId: number | null;
        thumbnailVersion: string;
    }[];
}>();
</script>

<template>
    <Head title="Duplicates" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Duplicates</h1>
            <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">Cards you own more than once — your trade list starts here.</p>

        <p v-if="groups.length === 0" class="mt-10 text-center text-gray-500">No duplicates — every card is one of a kind.</p>

        <div class="mt-4 flex flex-col gap-3">
            <Link
                v-for="group in groups"
                :key="`${group.itemId}`"
                :href="`/items/${group.itemId}`"
                class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm hover:bg-gray-50"
            >
                <img
                    v-if="group.thumbnailImageId"
                    :src="`/thumbnails/${group.thumbnailImageId}?v=${group.thumbnailVersion}`"
                    :alt="group.title"
                    class="h-20 w-16 rounded-lg bg-gray-100 object-contain"
                />
                <div v-else class="h-20 w-16 rounded-lg bg-gray-200"></div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-gray-900">{{ group.title }}</p>
                    <p class="text-sm font-semibold text-blue-700">×{{ group.copies }} owned</p>
                </div>
                <span class="text-sm font-semibold text-blue-600">View</span>
            </Link>
        </div>
    </div>
</template>
