<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    batches: {
        id: number;
        label: string;
        source: string;
        captureFlag: string | null;
        uploadedAt: string;
        itemCount: number;
        processedCount: number;
        needsReviewCount: number;
        pendingCount: number;
        processingTime: string | null;
    }[];
    unbatchedCount: number;
}>();
</script>

<template>
    <Head title="Batches" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Batches</h1>
            <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
        </div>

        <p v-if="batches.length === 0" class="mt-10 text-center text-gray-500">
            No batches yet — Bulk Capture sessions and scanner PDFs appear here.
        </p>

        <div class="mt-4 flex flex-col gap-3">
            <Link
                v-for="batch in batches"
                :key="batch.id"
                :href="`/batches/${batch.id}`"
                class="rounded-xl bg-white p-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <p class="min-w-0 truncate font-semibold text-gray-900">{{ batch.label }}</p>
                    <span class="ml-2 shrink-0 text-sm font-semibold text-blue-600">View</span>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    {{ batch.source }} · {{ batch.uploadedAt }}
                    <span v-if="batch.captureFlag" class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs font-semibold text-red-700">
                        🚩 {{ batch.captureFlag.replace(/_/g, ' ') }}
                    </span>
                </p>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <span class="text-gray-700">{{ batch.itemCount }} item(s)</span>
                    <span class="text-green-700">{{ batch.processedCount }} processed</span>
                    <span v-if="batch.needsReviewCount" class="text-amber-700">{{ batch.needsReviewCount }} needs review</span>
                    <span v-if="batch.pendingCount" class="text-gray-500">{{ batch.pendingCount }} pending</span>
                </div>
                <p v-if="batch.processingTime" class="mt-1 text-xs text-gray-400">
                    Processed in {{ batch.processingTime }}
                </p>
            </Link>
        </div>

        <p v-if="unbatchedCount" class="mt-6 text-center text-sm text-gray-400">
            Plus {{ unbatchedCount }} item(s) captured individually (not in any batch).
        </p>
    </div>
</template>
