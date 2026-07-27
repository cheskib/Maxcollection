<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

defineProps<{
    stats: {
        itemsCaptured: number;
        itemsProcessed: number;
        needsReview: number;
        picturesUploaded: number;
        unprocessed: number;
    };
}>();

const page = usePage<{ flash: { status: string | null } }>();

function processItems(): void {
    router.post('/process');
}

function logout(): void {
    router.post('/logout');
}
</script>

<template>
    <Head title="Home" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <h1 class="text-center text-2xl font-bold text-gray-900">Max Collection</h1>

        <p v-if="page.props.flash.status" class="mt-4 rounded-lg bg-green-50 p-3 text-center text-sm text-green-700">
            {{ page.props.flash.status }}
        </p>

        <div class="mt-6 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <p class="text-3xl font-bold text-gray-900">{{ stats.itemsCaptured }}</p>
                <p class="mt-1 text-sm text-gray-500">Items Captured</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <p class="text-3xl font-bold text-gray-900">{{ stats.itemsProcessed }}</p>
                <p class="mt-1 text-sm text-gray-500">Items Processed</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <p class="text-3xl font-bold" :class="stats.needsReview ? 'text-amber-600' : 'text-gray-900'">{{ stats.needsReview }}</p>
                <p class="mt-1 text-sm text-gray-500">Needs Review</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <p class="text-3xl font-bold text-gray-900">{{ stats.picturesUploaded }}</p>
                <p class="mt-1 text-sm text-gray-500">Pictures Uploaded</p>
            </div>
        </div>

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Add to collection</p>
        <div class="mt-2 grid grid-cols-2 gap-3">
            <Link href="/capture" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">📷</span>
                <p class="mt-1 font-semibold text-gray-900">Capture Item</p>
                <p class="text-xs text-gray-500">One at a time</p>
            </Link>
            <Link href="/capture/bulk" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">🗃️</span>
                <p class="mt-1 font-semibold text-gray-900">Bulk Capture</p>
                <p class="text-xs text-gray-500">Photos or scanner PDF</p>
            </Link>
        </div>

        <button
            class="mt-3 w-full rounded-xl py-4 font-semibold text-white shadow-sm"
            :class="stats.unprocessed ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-300'"
            :disabled="!stats.unprocessed"
            @click="processItems"
        >
            <template v-if="stats.unprocessed">▶ Process {{ stats.unprocessed }} waiting item(s)</template>
            <template v-else>Nothing waiting to process</template>
        </button>

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Your collection</p>
        <div class="mt-2 grid grid-cols-2 gap-3">
            <Link href="/items" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">🗂️</span>
                <p class="mt-1 font-semibold text-gray-900">Processed Items</p>
            </Link>
            <Link href="/browse" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">⚾</span>
                <p class="mt-1 font-semibold text-gray-900">Browse by Sport</p>
            </Link>
            <Link href="/review" class="relative rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">🔍</span>
                <p class="mt-1 font-semibold text-gray-900">Needs Review</p>
                <span
                    v-if="stats.needsReview"
                    class="absolute right-3 top-3 rounded-full bg-amber-500 px-2 py-0.5 text-xs font-bold text-white"
                >
                    {{ stats.needsReview }}
                </span>
            </Link>
            <Link href="/batches" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">📦</span>
                <p class="mt-1 font-semibold text-gray-900">Batches</p>
            </Link>
            <Link href="/collections" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">🗄️</span>
                <p class="mt-1 font-semibold text-gray-900">Collections</p>
            </Link>
            <Link href="/sets" class="rounded-xl bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                <span class="text-2xl">📚</span>
                <p class="mt-1 font-semibold text-gray-900">Sets</p>
            </Link>
        </div>

        <div class="mt-8 flex items-center justify-center gap-6 text-sm">
            <Link href="/settings" class="font-medium text-gray-500 hover:text-gray-700">Settings</Link>
            <button class="font-medium text-gray-500 hover:text-gray-700" @click="logout">Logout</button>
        </div>
    </div>
</template>
