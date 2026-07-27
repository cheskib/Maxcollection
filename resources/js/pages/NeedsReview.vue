<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    items: {
        id: number;
        thumbnailImageId: number | null;
        thumbnailRotation: number;
        title: string;
        reason: string;
        confidence: number | null;
        processedAt: string | null;
    }[];
}>();
</script>

<template>
    <Head title="Needs Review" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Needs Review</h1>
            <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
        </div>

        <p v-if="items.length === 0" class="mt-10 text-center text-gray-500">Nothing needs review.</p>

        <div class="mt-4 flex flex-col gap-3">
            <div v-for="item in items" :key="item.id" class="rounded-xl bg-white p-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <img
                        v-if="item.thumbnailImageId"
                        :src="`/thumbnails/${item.thumbnailImageId}?v=${item.thumbnailRotation}`"
                        :alt="item.title"
                        class="h-20 w-16 rounded-lg bg-gray-100 object-contain"
                    />
                    <div v-else class="h-20 w-16 rounded-lg bg-gray-200"></div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-gray-900">{{ item.title }}</p>
                        <p class="text-sm text-amber-700">{{ item.reason }}</p>
                        <p class="text-xs text-gray-400">
                            <span v-if="item.confidence !== null">{{ Math.round(item.confidence) }}% · </span>{{ item.processedAt }}
                        </p>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <Link
                        :href="`/items/${item.id}`"
                        class="flex-1 rounded-lg bg-gray-100 py-2 text-center text-sm font-semibold text-gray-700 hover:bg-gray-200"
                    >
                        View
                    </Link>
                    <Link
                        :href="`/items/${item.id}/edit`"
                        class="flex-1 rounded-lg bg-blue-600 py-2 text-center text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Edit
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
