<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface ProcessedItem {
    id: number;
    thumbnailImageId: number | null;
    title: string;
    category: string;
    confidence: number | null;
    processedAt: string | null;
}

const props = defineProps<{
    items: ProcessedItem[];
    sort: string;
    search?: string;
}>();

const search = ref(props.search ?? '');
const sort = ref(props.sort);

function apply(): void {
    router.get('/items', { q: search.value || undefined, sort: sort.value }, { preserveState: true });
}
</script>

<template>
    <Head title="Processed Items" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Processed Items</h1>
            <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
        </div>

        <form class="mt-4 flex gap-2" @submit.prevent="apply">
            <input
                v-model="search"
                type="search"
                placeholder="Search…"
                class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2"
            />
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">
                Search
            </button>
        </form>

        <select v-model="sort" class="mt-3 rounded-lg border border-gray-300 px-3 py-2 text-sm" @change="apply">
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="title">By title</option>
        </select>

        <p v-if="items.length === 0" class="mt-10 text-center text-gray-500">No processed items yet.</p>

        <div class="mt-4 flex flex-col gap-3">
            <Link
                v-for="item in items"
                :key="item.id"
                :href="`/items/${item.id}`"
                class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm"
            >
                <img
                    v-if="item.thumbnailImageId"
                    :src="`/thumbnails/${item.thumbnailImageId}`"
                    :alt="item.title"
                    class="h-16 w-16 rounded-lg object-cover"
                />
                <div v-else class="h-16 w-16 rounded-lg bg-gray-200"></div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-gray-900">{{ item.title }}</p>
                    <p class="text-sm text-gray-500">{{ item.category }}</p>
                    <p class="text-xs text-gray-400">
                        <span v-if="item.confidence !== null">{{ Math.round(item.confidence) }}% · </span>{{ item.processedAt }}
                    </p>
                </div>
                <span class="text-sm font-semibold text-blue-600">View</span>
            </Link>
        </div>
    </div>
</template>
