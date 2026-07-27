<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    collections: { id: number; name: string; itemCount: number }[];
    unassignedCount: number;
}>();
</script>

<template>
    <Head title="Collections" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Collections</h1>
            <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
        </div>

        <div class="mt-4 flex flex-col gap-3">
            <Link
                v-for="collection in collections"
                :key="collection.id"
                :href="`/collections/${collection.id}`"
                class="flex items-center justify-between rounded-xl bg-white p-4 shadow-sm hover:bg-gray-50"
            >
                <span class="font-semibold text-gray-900">{{ collection.name }}</span>
                <span class="text-sm text-gray-500">{{ collection.itemCount }} item(s) ›</span>
            </Link>

            <Link
                v-if="unassignedCount"
                href="/collections/unassigned"
                class="flex items-center justify-between rounded-xl bg-gray-100 p-4 hover:bg-gray-200"
            >
                <span class="font-semibold text-gray-600">Unassigned</span>
                <span class="text-sm text-gray-500">{{ unassignedCount }} item(s) ›</span>
            </Link>
        </div>
    </div>
</template>
