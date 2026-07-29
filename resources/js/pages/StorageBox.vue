<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    box: {
        code: string;
        status: string;
        closedAt: string | null;
        bagCount: number | null;
        sectionCount: number | null;
        cardCount: number | null;
        sections: {
            position: number;
            divider: string | null;
            dividerCode: string | null;
            bags: { batchId: number; code: string; itemCount: number; removedCount: number }[];
        }[];
    };
}>();
</script>

<template>
    <Head :title="box.code" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">{{ box.code }}</h1>
            <Link href="/storage" class="text-sm font-semibold text-blue-600">Storage</Link>
        </div>
        <p class="mt-1 text-sm" :class="box.status === 'closed' ? 'text-gray-500' : 'text-green-700'">
            <template v-if="box.status === 'closed'">
                Completed {{ box.closedAt }} · now holds {{ box.bagCount }} bags · {{ box.sectionCount }} sections · {{ box.cardCount }} cards
            </template>
            <template v-else>Open — being packed</template>
        </p>

        <div v-for="section in box.sections" :key="section.position" class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="font-semibold" :class="section.divider ? 'text-gray-900' : 'text-amber-700'">
                    Section {{ section.position }} · {{ section.divider ?? 'No Divider Assigned' }}
                </p>
                <span v-if="section.dividerCode" class="font-mono text-xs text-gray-400">{{ section.dividerCode }}</span>
            </div>
            <div class="mt-2 divide-y divide-gray-100">
                <Link
                    v-for="bag in section.bags"
                    :key="bag.batchId"
                    :href="`/batches/${bag.batchId}`"
                    class="flex items-center justify-between py-2 text-sm hover:bg-gray-50"
                >
                    <span class="font-mono font-semibold text-gray-900">{{ bag.code }}</span>
                    <span class="text-gray-500">
                        {{ bag.itemCount }} card(s)<span v-if="bag.removedCount" class="text-amber-600"> · {{ bag.removedCount }} removed</span> ›
                    </span>
                </Link>
                <p v-if="!section.bags.length" class="py-2 text-sm text-gray-400">No bags.</p>
            </div>
        </div>
    </div>
</template>
