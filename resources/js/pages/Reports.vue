<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    pipeline: {
        capturing: number;
        needsReview: number;
        processedUnbagged: number;
        bagged: number;
        boxed: number;
        relocated: number;
        gone: number;
        soldTotal: number;
    };
    days: {
        date: string;
        label: string;
        captured: number;
        processed: number;
        bagsFinalized: number;
        bagsBoxed: number;
        boxesCompleted: number;
        reinstated: number;
        removals: { reason: string; count: number; soldTotal: number | null }[];
    }[];
}>();

function hasActivity(day: {
    captured: number;
    processed: number;
    bagsFinalized: number;
    bagsBoxed: number;
    boxesCompleted: number;
    reinstated: number;
    removals: unknown[];
}): boolean {
    return (
        day.captured + day.processed + day.bagsFinalized + day.bagsBoxed + day.boxesCompleted + day.reinstated > 0 ||
        day.removals.length > 0
    );
}
</script>

<template>
    <Head title="Reports" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Reports</h1>
            <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
        </div>

<!-- Inbound: where every card stands right now -->
        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400">Pipeline — where everything is now</p>
        <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
            <div class="flex items-center justify-between p-3 text-sm">
                <p class="text-gray-700">📷 Capturing / processing</p>
                <span class="font-bold text-gray-900">{{ pipeline.capturing }}</span>
            </div>
            <div class="flex items-center justify-between p-3 text-sm">
                <p class="text-gray-700">🔍 Needs review</p>
                <span class="font-bold" :class="pipeline.needsReview ? 'text-amber-700' : 'text-gray-900'">{{ pipeline.needsReview }}</span>
            </div>
            <div class="flex items-center justify-between p-3 text-sm">
                <p class="text-gray-700">✅ Processed, not yet bagged</p>
                <span class="font-bold text-gray-900">{{ pipeline.processedUnbagged }}</span>
            </div>
            <div class="flex items-center justify-between p-3 text-sm">
                <p class="text-gray-700">🏷️ In bags, not yet boxed</p>
                <span class="font-bold text-gray-900">{{ pipeline.bagged }}</span>
            </div>
            <div class="flex items-center justify-between p-3 text-sm">
                <p class="text-gray-700">📦 Boxed</p>
                <span class="font-bold text-gray-900">{{ pipeline.boxed }}</span>
            </div>
        </div>

<!-- Outbound: what has left the bags -->
        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Outbound</p>
        <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
            <div class="flex items-center justify-between p-3 text-sm">
                <p class="text-gray-700">🟡 Relocated (still owned)</p>
                <span class="font-bold text-gray-900">{{ pipeline.relocated }}</span>
            </div>
            <div class="flex items-center justify-between p-3 text-sm">
                <p class="text-gray-700">🔴 Out of collection (sold / gift / lost)</p>
                <span class="font-bold text-gray-900">{{ pipeline.gone }}</span>
            </div>
            <div v-if="pipeline.soldTotal > 0" class="flex items-center justify-between p-3 text-sm">
                <p class="text-gray-700">💰 Total realized from sales</p>
                <span class="font-bold text-green-700">${{ pipeline.soldTotal.toLocaleString() }}</span>
            </div>
        </div>

<!-- The daily ledger -->
        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Daily activity — last 14 days</p>
        <div class="mt-2 flex flex-col gap-2">
            <div v-for="day in days" :key="day.date" class="rounded-xl bg-white p-3 shadow-sm">
                <p class="text-sm font-bold text-gray-900">{{ day.label }}</p>
                <template v-if="hasActivity(day)">
                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-600">
                        <span v-if="day.captured">📷 {{ day.captured }} captured</span>
                        <span v-if="day.processed">🤖 {{ day.processed }} processed</span>
                        <span v-if="day.bagsFinalized">🏷️ {{ day.bagsFinalized }} bag(s) finalized</span>
                        <span v-if="day.bagsBoxed">📦 {{ day.bagsBoxed }} bag(s) boxed</span>
                        <span v-if="day.boxesCompleted">✅ {{ day.boxesCompleted }} box(es) completed</span>
                        <span v-if="day.reinstated" class="text-green-700">⎌ {{ day.reinstated }} reinstated</span>
                    </div>
                    <div v-if="day.removals.length" class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs">
                        <span v-for="removal in day.removals" :key="removal.reason" class="font-semibold text-amber-700">
                            📤 {{ removal.count }} {{ removal.reason
                            }}<template v-if="removal.soldTotal"> (${{ removal.soldTotal.toLocaleString() }})</template>
                        </span>
                    </div>
                </template>
                <p v-else class="mt-1 text-xs text-gray-400">No activity.</p>
            </div>
        </div>
    </div>
</template>
