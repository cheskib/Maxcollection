<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    labels: { code: string; label: string | null; svg: string }[];
}>();

function printSheet(): void {
    window.print();
}
</script>

<template>
    <Head title="Label Sheet" />
    <div class="mx-auto w-full max-w-3xl px-4 py-8">
        <div class="flex items-center justify-between print:hidden">
            <h1 class="text-2xl font-bold text-gray-900">Label Sheet · {{ labels.length }} label(s)</h1>
            <div class="flex gap-4">
                <button class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700" @click="printSheet">
                    🖨 Print
                </button>
                <Link href="/storage/labels" class="self-center text-sm font-semibold text-blue-600">Back</Link>
            </div>
        </div>
        <p class="mt-2 text-sm text-gray-500 print:hidden">
            Print on sticker sheets, then stick one label on each empty bag, box, or divider card.
        </p>

        <div class="mt-6 grid grid-cols-3 gap-4 print:mt-0 print:gap-2">
            <div
                v-for="item in labels"
                :key="item.code"
                class="flex flex-col items-center rounded-lg border border-gray-200 p-3 print:break-inside-avoid print:rounded-none"
            >
                <p v-if="item.label" class="mb-1 text-sm font-bold text-gray-900">{{ item.label }}</p>
                <div class="w-full overflow-hidden [&>svg]:h-12 [&>svg]:w-full" v-html="item.svg"></div>
                <p class="mt-1 font-mono text-sm font-semibold tracking-wider text-gray-900">{{ item.code }}</p>
            </div>
        </div>
    </div>
</template>
