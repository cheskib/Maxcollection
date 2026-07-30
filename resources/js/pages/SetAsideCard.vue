<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ svg: string }>();

function printCard(): void {
    window.print();
}
</script>

<template>
    <Head title="SET-ASIDE Card" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between print:hidden">
            <h1 class="text-2xl font-bold text-gray-900">SET-ASIDE Card</h1>
            <Link href="/bagging" class="text-sm font-semibold text-blue-600">‹ Bagging</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500 print:hidden">
            Print, laminate, keep at the bagging station. Scanning it confirms a flagged bin left the line.
        </p>
        <button
            class="mt-4 w-full rounded-xl bg-blue-600 py-3 font-semibold text-white shadow-sm hover:bg-blue-700 print:hidden"
            @click="printCard"
        >
            🖨 Print
        </button>

        <!-- The card itself: bold, unmistakable, scannable. -->
        <div class="mt-6 rounded-xl border-4 border-red-600 bg-white p-6 text-center">
            <p class="text-xs font-bold uppercase tracking-widest text-gray-400">Max Collection</p>
            <p class="mt-2 text-4xl font-black tracking-tight text-red-600">SET ASIDE</p>
            <p class="mt-1 text-sm font-semibold text-gray-600">Flagged bin confirmed off the line</p>
            <div class="mt-4 flex justify-center overflow-x-auto" v-html="svg"></div>
            <p class="mt-2 font-mono text-sm font-bold text-gray-900">SET-ASIDE</p>
        </div>
    </div>
</template>
