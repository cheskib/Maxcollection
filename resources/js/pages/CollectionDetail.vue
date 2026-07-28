<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    collection: { id: number | null; name: string; value: { from: number | null; to: number | null } };
    items: {
        id: number;
        thumbnailImageId: number | null;
        thumbnailVersion: string;
        title: string;
        category: string;
        status: string;
        value: { from: number | null; to: number | null; isOurs: boolean };
    }[];
    sort: string;
}>();

const sort = ref(props.sort);

function applySort(): void {
    const slug = props.collection.id === null ? 'unassigned' : String(props.collection.id);
    router.get(`/collections/${slug}`, sort.value === 'newest' ? {} : { sort: sort.value }, { preserveState: true });
}

function money(from: number | null, to: number | null): string | null {
    if (from === null && to === null) return null;
    const fmt = (v: number) => `$${v.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
    if (from !== null && to !== null) return from === to ? fmt(from) : `${fmt(from)} – ${fmt(to)}`;
    return fmt((from ?? to) as number);
}
</script>

<template>
    <Head :title="collection.name" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="min-w-0 truncate text-2xl font-bold text-gray-900">{{ collection.name }}</h1>
            <Link href="/collections" class="ml-2 shrink-0 text-sm font-semibold text-blue-600">All collections</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">
            {{ items.length }} item(s)
            <span v-if="money(collection.value.from, collection.value.to)" class="font-semibold text-green-700">
                · {{ money(collection.value.from, collection.value.to) }}
            </span>
        </p>

        <select v-model="sort" class="mt-3 rounded-lg border border-gray-300 px-3 py-2 text-sm" @change="applySort">
            <option value="newest">Newest first</option>
            <option value="value">Highest value first</option>
        </select>

        <p v-if="items.length === 0" class="mt-10 text-center text-gray-500">Nothing in this collection yet.</p>

        <div class="mt-4 flex flex-col gap-3">
            <Link
                v-for="item in items"
                :key="item.id"
                :href="`/items/${item.id}`"
                class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm"
            >
                <img
                    v-if="item.thumbnailImageId"
                    :src="`/thumbnails/${item.thumbnailImageId}?v=${item.thumbnailVersion}`"
                    :alt="item.title"
                    class="h-20 w-16 rounded-lg bg-gray-100 object-contain"
                />
                <div v-else class="h-20 w-16 rounded-lg bg-gray-200"></div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-gray-900">{{ item.title }}</p>
                    <p class="text-sm text-gray-500">{{ item.category }}</p>
                    <p v-if="money(item.value.from, item.value.to)" class="text-sm font-medium text-green-700">
                        {{ money(item.value.from, item.value.to) }}
                        <span v-if="!item.value.isOurs" class="font-normal text-gray-400">(AI)</span>
                    </p>
                </div>
                <span class="text-sm font-semibold text-blue-600">View</span>
            </Link>
        </div>
    </div>
</template>
