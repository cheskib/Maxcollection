<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    collection: { id: number | null; name: string };
    items: {
        id: number;
        thumbnailImageId: number | null;
        thumbnailVersion: string;
        title: string;
        category: string;
        status: string;
    }[];
}>();
</script>

<template>
    <Head :title="collection.name" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="min-w-0 truncate text-2xl font-bold text-gray-900">{{ collection.name }}</h1>
            <Link href="/collections" class="ml-2 shrink-0 text-sm font-semibold text-blue-600">All collections</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">{{ items.length }} item(s)</p>

        <p v-if="items.length === 0" class="mt-10 text-center text-gray-500">Nothing in this collection yet.</p>

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
                    <p class="truncate font-semibold text-gray-900">{{ item.title }}</p>
                    <p class="text-sm text-gray-500">{{ item.category }}</p>
                </div>
                <span class="text-sm font-semibold text-blue-600">View</span>
            </Link>
        </div>
    </div>
</template>
