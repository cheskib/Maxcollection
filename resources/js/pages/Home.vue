<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';

defineProps<{
    stats: {
        itemsCaptured: number;
        itemsProcessed: number;
        needsReview: number;
        picturesUploaded: number;
    };
}>();

const page = usePage<{ flash: { status: string | null } }>();

const navigation = [
    { label: 'Processed Items', href: '/items' },
    { label: 'Needs Review', href: '/review' },
    { label: 'Batches', href: '/batches' },
    { label: 'Settings', href: '/settings' },
] as const;

function open(href: string): void {
    router.visit(href);
}

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
                <p class="text-3xl font-bold text-amber-600">{{ stats.needsReview }}</p>
                <p class="mt-1 text-sm text-gray-500">Needs Review</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <p class="text-3xl font-bold text-gray-900">{{ stats.picturesUploaded }}</p>
                <p class="mt-1 text-sm text-gray-500">Pictures Uploaded</p>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-3">
            <button
                class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700"
                @click="open('/capture')"
            >
                Capture Item
            </button>
            <button
                class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700"
                @click="open('/capture/bulk')"
            >
                Bulk Capture
            </button>
            <button
                class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700"
                @click="processItems"
            >
                Process Items
            </button>
            <button
                v-for="action in navigation"
                :key="action.label"
                class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700"
                @click="open(action.href)"
            >
                {{ action.label }}
            </button>

            <button
                class="w-full rounded-lg bg-gray-200 py-3 font-semibold text-gray-700 hover:bg-gray-300"
                @click="logout"
            >
                Logout
            </button>
        </div>
    </div>
</template>
