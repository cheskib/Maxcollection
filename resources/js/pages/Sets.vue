<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    sets: { id: number; name: string; cardCount: number; hasDescription: boolean }[];
}>();
</script>

<template>
    <Head title="Sets" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Sets</h1>
            <Link href="/" class="ml-2 shrink-0 text-sm font-semibold text-blue-600">Home</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">
            A written history of every card set in your collection — built automatically as you scan.
        </p>

        <p v-if="sets.length === 0" class="mt-6 rounded-xl bg-white p-4 text-sm text-gray-500 shadow-sm">
            No sets yet. Profiles appear here as soon as sports cards are processed.
        </p>

        <div class="mt-4 flex flex-col gap-3">
            <Link
                v-for="set in sets"
                :key="set.id"
                :href="`/sets/${set.id}`"
                class="flex items-center justify-between rounded-xl bg-white p-4 shadow-sm hover:bg-gray-50"
            >
                <div class="min-w-0">
                    <p class="truncate font-semibold text-gray-900">{{ set.name }}</p>
                    <p class="text-sm text-gray-500">
                        {{ set.cardCount }} card(s) owned
                        <span v-if="!set.hasDescription" class="text-gray-400"> · history pending</span>
                    </p>
                </div>
                <span class="ml-3 shrink-0 text-sm font-semibold text-blue-600">View</span>
            </Link>
        </div>
    </div>
</template>
