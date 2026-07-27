<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    batch: { id: number; label: string; uploadedAt: string };
    items: {
        id: number;
        thumbnailImageId: number | null;
        thumbnailRotation: number;
        title: string;
        status: string;
        reason: string | null;
        confidence: number | null;
    }[];
}>();
</script>

<template>
    <Head :title="batch.label" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="min-w-0 truncate text-2xl font-bold text-gray-900">{{ batch.label }}</h1>
            <Link href="/batches" class="ml-2 shrink-0 text-sm font-semibold text-blue-600">All batches</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">Uploaded {{ batch.uploadedAt }} · {{ items.length }} item(s)</p>

        <div class="mt-4 flex flex-col gap-3">
            <Link
                v-for="item in items"
                :key="item.id"
                :href="`/items/${item.id}`"
                class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm"
            >
                <img
                    v-if="item.thumbnailImageId"
                    :src="`/thumbnails/${item.thumbnailImageId}?v=${item.thumbnailRotation}`"
                    :alt="item.title"
                    class="h-20 w-16 rounded-lg bg-gray-100 object-contain"
                />
                <div v-else class="h-20 w-16 rounded-lg bg-gray-200"></div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-gray-900">{{ item.title }}</p>
                    <p
                        class="text-sm"
                        :class="{
                            'text-green-700': item.status === 'Processed',
                            'text-amber-700': item.status === 'Needs Review',
                            'text-gray-500': item.status === 'Pending',
                        }"
                    >
                        {{ item.status }}<span v-if="item.reason"> · {{ item.reason }}</span>
                    </p>
                    <p v-if="item.confidence !== null" class="text-xs text-gray-400">{{ Math.round(item.confidence) }}% confidence</p>
                </div>
                <span class="text-sm font-semibold text-blue-600">View</span>
            </Link>
        </div>
    </div>
</template>
