<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

interface Row {
    value: string;
    count: number;
}

const props = defineProps<{
    overview: {
        totalItems: number;
        processed: number;
        needsReview: number;
        cleanRate: number | null;
        avgConfidence: number | null;
        failedJobs: number;
    };
    byYear: Row[];
    byManufacturer: Row[];
    bySport: Row[];
    reviewReasons: { reason: string; count: number }[];
}>();

function width(rows: Row[], count: number): string {
    const max = Math.max(...rows.map((row) => row.count), 1);
    return `${Math.max(4, Math.round((count / max) * 100))}%`;
}
</script>

<template>
    <Head title="Stats" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Stats</h1>
            <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <p class="text-3xl font-bold text-gray-900">{{ overview.processed }}</p>
                <p class="mt-1 text-sm text-gray-500">Processed</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <p class="text-3xl font-bold" :class="(overview.cleanRate ?? 100) >= 90 ? 'text-green-700' : 'text-amber-600'">
                    {{ overview.cleanRate !== null ? `${overview.cleanRate}%` : '—' }}
                </p>
                <p class="mt-1 text-sm text-gray-500">Processed clean (no review)</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <p class="text-3xl font-bold text-gray-900">{{ overview.avgConfidence !== null ? `${overview.avgConfidence}%` : '—' }}</p>
                <p class="mt-1 text-sm text-gray-500">Average confidence</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <p class="text-3xl font-bold" :class="overview.failedJobs ? 'text-red-600' : 'text-gray-900'">{{ overview.failedJobs }}</p>
                <p class="mt-1 text-sm text-gray-500">Failed AI jobs (all time)</p>
            </div>
        </div>

        <template v-if="reviewReasons.length">
            <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Needs review — why</p>
            <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                <div v-for="row in reviewReasons" :key="row.reason" class="flex items-center justify-between p-3 text-sm">
                    <p class="text-gray-700">{{ row.reason }}</p>
                    <span class="font-bold text-amber-700">{{ row.count }}</span>
                </div>
            </div>
        </template>

        <template v-for="section in [
            { title: 'Cards by year', rows: byYear },
            { title: 'Cards by manufacturer', rows: byManufacturer },
            { title: 'Cards by sport', rows: bySport },
        ]" :key="section.title">
            <template v-if="section.rows.length">
                <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ section.title }}</p>
                <div class="mt-2 rounded-xl bg-white p-3 shadow-sm">
                    <div v-for="row in section.rows" :key="row.value" class="py-1">
                        <div class="flex items-center justify-between text-sm">
                            <p class="text-gray-700">{{ row.value }}</p>
                            <span class="font-semibold text-gray-900">{{ row.count }}</span>
                        </div>
                        <div class="mt-0.5 h-1.5 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-blue-500" :style="{ width: width(section.rows, row.count) }"></div>
                        </div>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>
