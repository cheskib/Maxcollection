<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

// The landing grid mirrors the owner's concept — an aspirational sweep
// of everything collectible, not the app's category list.
const CATEGORIES = [
    { label: 'Sports Cards', icon: 'M4 7c5-4 11-4 16 0M4 17c5 4 11 4 16 0M4 7c-1.5 3-1.5 7 0 10M20 7c1.5 3 1.5 7 0 10M9 5.5v13M15 5.5v13' },
    { label: 'All Cards', icon: 'M7 4h9a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1ZM9 2h9a1 1 0 0 1 1 1v13' },
    { label: 'Comics', icon: 'M12 5c-2-1.5-5-1.5-7-.5v14c2-1 5-1 7 .5 2-1.5 5-1.5 7-.5v-14c-2-1-5-1-7 .5Zm0 0v14' },
    { label: 'Games', icon: 'M6 9h12a3 3 0 0 1 3 3v3a3 3 0 0 1-3 3c-1.5 0-2.5-1-3-2h-6c-.5 1-1.5 2-3 2a3 3 0 0 1-3-3v-3a3 3 0 0 1 3-3Zm2 2v4M6 13h4M15.5 12h.01M17.5 14h.01' },
    { label: 'Coins', icon: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 3a6 6 0 1 1 0 12 6 6 0 0 1 0-12Z' },
    { label: 'Stamps', icon: 'M5 5h14v14H5V5Zm3 3h8v8H8V8ZM5 9h1M5 12h1M5 15h1M18 9h1M18 12h1M18 15h1M9 5v1M12 5v1M15 5v1M9 18v1M12 18v1M15 18v1' },
    { label: 'Antiques', icon: 'M9 3h6M10 3c0 3-2 4-2 7 0 2.5 1 4 1 6.5S8 20 8 21h8c0-1-1-2-1-4.5S16 12.5 16 10c0-3-2-4-2-7M9 12h6' },
    { label: 'Documents', icon: 'M7 3h7l4 4v14H7V3Zm7 0v4h4M10 11h5M10 14h5M10 17h3' },
    { label: 'Historical Items', icon: 'M8 3h8M8 21h8M9 3c0 4 6 5 6 9s-6 5-6 9M15 3c0 4-6 5-6 9s6 5 6 9' },
    { label: 'And More', icon: 'M5 21V9l7-5 7 5v12M3 21h18M9 21v-6h6v6M12 8h.01' },
];
</script>

<template>
    <Head title="Max Collections" />
    <div class="landing flex min-h-screen flex-col items-center px-6 py-14 text-center">
        <p class="serif text-xl tracking-[0.3em]" style="color: #d4a643">WELCOME TO</p>

        <div class="mt-3 flex w-64 items-center gap-3" style="color: rgba(212, 166, 67, 0.4)">
            <div class="h-px flex-1 bg-current"></div>
            <span style="color: #d4a643">❖</span>
            <div class="h-px flex-1 bg-current"></div>
        </div>

        <h1 class="serif mt-6 text-7xl font-bold tracking-wide" style="color: #d4a643">MAX</h1>
        <p class="mt-3 text-2xl tracking-[0.35em] text-white">COLLECTIONS</p>

        <div class="mt-5 flex w-72 items-center gap-4" style="color: rgba(212, 166, 67, 0.4)">
            <div class="h-px flex-1 bg-current"></div>
            <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="#d4a643" stroke-width="1.3" stroke-linecap="round">
                <path d="M4 21h16M5 21v-9m3 9v-9m4 9v-9m4 9v-9m3 9v-9M3 12h18M12 3l9 6H3l9-6Z" />
            </svg>
            <div class="h-px flex-1 bg-current"></div>
        </div>

        <p class="serif mt-8 text-4xl leading-snug text-white">
            One Home for<br />
            Everything You Collect.
        </p>

        <p class="mt-6 max-w-sm text-lg leading-relaxed" style="color: #c3c8d2">
            Max Collections is your personal catalog for sports cards, all kinds of cards, comics, games, stamps, coins,
            antiques, documents and all your most valuable finds.
        </p>

        <div class="mt-10 grid w-full max-w-md grid-cols-5 gap-x-1 gap-y-8">
            <div v-for="category in CATEGORIES" :key="category.label" class="flex flex-col items-center gap-2">
                <div class="flex h-14 w-12 items-end justify-center rounded-t-full border pb-1" style="border-color: rgba(212, 166, 67, 0.35)">
                    <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="#d4a643" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                        <path :d="category.icon" />
                    </svg>
                </div>
                <p class="text-[0.6rem] font-semibold leading-tight tracking-wider" style="color: #e4c876">{{ category.label.toUpperCase() }}</p>
            </div>
        </div>

        <div class="mt-10 h-px w-56" style="background: rgba(212, 166, 67, 0.4)"></div>

        <p class="mt-6 text-base tracking-[0.2em] text-white">ORGANIZE. PRESERVE. CHERISH.</p>
        <p class="serif mt-2 text-lg tracking-[0.15em]" style="color: #d4a643">YOUR COLLECTION. YOUR LEGACY.</p>

        <Link
            href="/login"
            class="serif mt-10 block w-full max-w-sm rounded-xl border py-4 text-3xl font-semibold tracking-[0.25em] shadow-lg"
            style="
                background: linear-gradient(180deg, #f3dfa4 0%, #e2bd6b 50%, #c49a44 100%);
                border-color: #f6e7bd;
                color: #101826;
            "
        >
            ENTER
        </Link>
    </div>
</template>

<style scoped>
.landing {
    background:
        radial-gradient(ellipse 120% 60% at 50% 18%, rgba(64, 84, 120, 0.35) 0%, rgba(0, 0, 0, 0) 60%),
        radial-gradient(ellipse 140% 80% at 50% 45%, #101a2c 0%, #0b1220 55%, #070c15 100%);
}

.serif {
    font-family: Georgia, 'Times New Roman', serif;
}
</style>
