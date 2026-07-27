<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    total: number;
    categories: { value: string; label: string; count: number }[];
    sports: { value: string; count: number }[];
    cardTypes: { value: string; count: number }[];
    collections: {
        named: { id: number; name: string; count: number }[];
        unassigned: number;
    };
    sets: { id: number; name: string; count: number }[];
}>();

const search = ref('');

function goSearch(): void {
    router.get('/items', search.value ? { q: search.value } : {});
}
</script>

<template>
    <Head title="Processed Items Overview" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Processed Items</h1>
            <Link href="/" class="ml-2 shrink-0 text-sm font-semibold text-blue-600">Home</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">{{ total }} item(s) identified — tap any row to see just those.</p>

        <form class="mt-4 flex gap-2" @submit.prevent="goSearch">
            <input
                v-model="search"
                type="search"
                placeholder="Search players, sets, years…"
                class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
            />
            <button type="submit" class="shrink-0 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">
                Search
            </button>
        </form>

        <template v-if="categories.length > 1">
            <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">By category</p>
            <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                <Link
                    v-for="row in categories"
                    :key="row.value"
                    :href="`/items?category=${encodeURIComponent(row.value)}`"
                    class="flex items-center justify-between p-4 hover:bg-gray-50"
                >
                    <p class="text-sm text-gray-700">{{ row.label }}</p>
                    <span class="font-bold text-gray-900">{{ row.count }} ›</span>
                </Link>
            </div>
        </template>

        <template v-if="sports.length">
            <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">By sport</p>
            <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                <Link
                    v-for="row in sports"
                    :key="row.value"
                    :href="`/items?sport=${encodeURIComponent(row.value)}`"
                    class="flex items-center justify-between p-4 hover:bg-gray-50"
                >
                    <p class="text-sm text-gray-700">{{ row.value }}</p>
                    <span class="font-bold text-gray-900">{{ row.count }} ›</span>
                </Link>
            </div>
        </template>

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">By collection</p>
        <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
            <Link
                v-for="row in collections.named"
                :key="row.id"
                :href="`/items?collection=${row.id}`"
                class="flex items-center justify-between p-4 hover:bg-gray-50"
            >
                <p class="text-sm text-gray-700">{{ row.name }}</p>
                <span class="font-bold text-gray-900">{{ row.count }} ›</span>
            </Link>
            <Link
                v-if="collections.unassigned > 0"
                href="/items?collection=unassigned"
                class="flex items-center justify-between p-4 hover:bg-gray-50"
            >
                <p class="text-sm text-gray-500">Unassigned</p>
                <span class="font-bold text-gray-900">{{ collections.unassigned }} ›</span>
            </Link>
        </div>

        <template v-if="cardTypes.length">
            <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">By card type</p>
            <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                <Link
                    v-for="row in cardTypes"
                    :key="row.value"
                    :href="`/items?card_type=${encodeURIComponent(row.value)}`"
                    class="flex items-center justify-between p-4 hover:bg-gray-50"
                >
                    <p class="text-sm text-gray-700">{{ row.value }}</p>
                    <span class="font-bold text-gray-900">{{ row.count }} ›</span>
                </Link>
            </div>
        </template>

        <template v-if="sets.length">
            <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">By set</p>
            <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                <Link
                    v-for="set in sets"
                    :key="set.id"
                    :href="`/sets/${set.id}`"
                    class="flex items-center justify-between p-4 hover:bg-gray-50"
                >
                    <p class="text-sm text-gray-700">{{ set.name }}</p>
                    <span class="font-bold text-gray-900">{{ set.count }} ›</span>
                </Link>
            </div>
        </template>
    </div>
</template>
