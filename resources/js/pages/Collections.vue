<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    collections: { id: number; name: string; itemCount: number; value: { from: number | null; to: number | null } }[];
    unassignedCount: number;
    unassignedValue: { from: number | null; to: number | null };
}>();

function money(from: number | null, to: number | null): string | null {
    if (from === null && to === null) return null;
    const fmt = (v: number) => `$${v.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
    if (from !== null && to !== null) return from === to ? fmt(from) : `${fmt(from)} – ${fmt(to)}`;
    return fmt((from ?? to) as number);
}
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
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900">{{ collection.name }}</p>
                    <p v-if="money(collection.value.from, collection.value.to)" class="text-sm text-green-700">
                        {{ money(collection.value.from, collection.value.to) }}
                    </p>
                </div>
                <span class="ml-3 shrink-0 text-sm text-gray-500">{{ collection.itemCount }} item(s) ›</span>
            </Link>

            <Link
                v-if="unassignedCount"
                href="/collections/unassigned"
                class="flex items-center justify-between rounded-xl bg-gray-100 p-4 hover:bg-gray-200"
            >
                <div class="min-w-0">
                    <p class="font-semibold text-gray-600">Unassigned</p>
                    <p v-if="money(unassignedValue.from, unassignedValue.to)" class="text-sm text-green-700">
                        {{ money(unassignedValue.from, unassignedValue.to) }}
                    </p>
                </div>
                <span class="ml-3 shrink-0 text-sm text-gray-500">{{ unassignedCount }} item(s) ›</span>
            </Link>
        </div>
    </div>
</template>
