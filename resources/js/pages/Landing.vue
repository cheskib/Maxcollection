<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const CATEGORIES = [
    { label: 'Sports Cards', icon: 'M4 7c5-4 11-4 16 0M4 17c5 4 11 4 16 0M4 7c-1.5 3-1.5 7 0 10M20 7c1.5 3 1.5 7 0 10M9 5.5v13M15 5.5v13' },
    { label: 'All Cards', icon: 'M7 4h9a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1ZM9 2h9a1 1 0 0 1 1 1v13' },
    { label: 'Comics', icon: 'M12 5c-2-1.5-5-1.5-7-.5v14c2-1 5-1 7 .5 2-1.5 5-1.5 7-.5v-14c-2-1-5-1-7 .5Zm0 0v14' },
    { label: 'Coins', icon: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 3a6 6 0 1 1 0 12 6 6 0 0 1 0-12Z' },
    { label: 'Stamps', icon: 'M5 5h14v14H5V5Zm3 3h8v8H8V8ZM5 9h1M5 12h1M5 15h1M18 9h1M18 12h1M18 15h1M9 5v1M12 5v1M15 5v1M9 18v1M12 18v1M15 18v1' },
    { label: 'And More', icon: 'M4 9l8-5 8 5v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9Zm0 0l8 5 8-5M12 14v7' },
];
</script>

<template>
    <Head title="Max Collections" />
    <div class="landing flex min-h-screen flex-col items-center px-6 py-12 text-center">
        <!-- Monogram -->
        <div class="mt-4 flex h-36 w-36 items-center justify-center rounded-full border-2" style="border-color: #d4a643">
            <div class="flex flex-col items-center">
                <svg viewBox="0 0 24 12" class="h-4 w-8" fill="#d4a643">
                    <path d="M2 10h20L20 3l-4 3-4-5-4 5-4-3z" />
                </svg>
                <span class="serif -mt-1 text-6xl font-bold" style="color: #d4a643">M</span>
            </div>
        </div>

        <h1 class="serif mt-6 text-6xl font-bold tracking-wide" style="color: #d4a643">MAX</h1>
        <p class="mt-2 text-2xl tracking-[0.35em] text-white">COLLECTIONS</p>

        <div class="mt-5 flex w-64 items-center gap-3" style="color: rgba(212, 166, 67, 0.4)">
            <div class="h-px flex-1 bg-current"></div>
            <span style="color: #d4a643">◇</span>
            <div class="h-px flex-1 bg-current"></div>
        </div>

        <p class="serif mt-8 text-2xl leading-snug" style="color: #d4a643">
            ONE HOME.<br />
            EVERYTHING YOU COLLECT.
        </p>

        <p class="mt-6 max-w-sm text-lg leading-relaxed" style="color: #c9cbd1">
            Max Collections is your personal catalog for every treasure you own. Sports cards, comics, coins, stamps and
            everything in between.
        </p>

        <div class="mt-8 flex w-40 items-center gap-3" style="color: rgba(212, 166, 67, 0.4)">
            <div class="h-px flex-1 bg-current"></div>
            <span style="color: #d4a643">◇</span>
            <div class="h-px flex-1 bg-current"></div>
        </div>

        <div class="mt-8 grid w-full max-w-sm grid-cols-3 gap-x-2 gap-y-7">
            <div v-for="category in CATEGORIES" :key="category.label" class="flex flex-col items-center gap-2">
                <svg viewBox="0 0 24 24" class="h-10 w-10" fill="none" stroke="#d4a643" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                    <path :d="category.icon" />
                </svg>
                <p class="text-xs font-semibold tracking-widest" style="color: #d4a643">{{ category.label.toUpperCase() }}</p>
            </div>
        </div>

        <p class="mt-10 text-sm tracking-[0.25em] text-white">ORGANIZE. PROTECT. PRESERVE.</p>
        <p class="mt-1 text-sm tracking-[0.25em]" style="color: #d4a643">YOUR COLLECTION. YOUR LEGACY.</p>

        <Link
            href="/login"
            class="serif mt-10 block w-full max-w-sm rounded-2xl py-4 text-2xl font-semibold tracking-[0.2em] text-gray-900 shadow-lg"
            style="background: linear-gradient(180deg, #e8c15a 0%, #d4a643 45%, #b9862e 100%)"
        >
            ENTER
        </Link>
    </div>
</template>

<style scoped>
.landing {
    background: radial-gradient(ellipse at 50% 30%, #1b1c21 0%, #141519 55%, #0e0f12 100%);
}

.serif {
    font-family: Georgia, 'Times New Roman', serif;
}
</style>
