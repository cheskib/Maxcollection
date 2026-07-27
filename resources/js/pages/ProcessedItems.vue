<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface ProcessedItem {
    id: number;
    thumbnailImageId: number | null;
    thumbnailVersion: string;
    title: string;
    category: string;
    confidence: number | null;
    processedAt: string | null;
}

type FilterField = 'category' | 'sport' | 'year' | 'team' | 'manufacturer' | 'card_type';

const props = defineProps<{
    items: ProcessedItem[];
    sort: string;
    search?: string;
    collection: string;
    collections: { id: number; name: string }[];
    filters: Record<FilterField, string>;
    filterOptions: Record<FilterField, string[]>;
}>();

const search = ref(props.search ?? '');
const sort = ref(props.sort);
const collection = ref(props.collection);
const filters = ref({ ...props.filters });

const FILTER_LABELS: Record<FilterField, string> = {
    category: 'Category',
    sport: 'Sport',
    year: 'Year',
    team: 'Team',
    manufacturer: 'Manufacturer',
    card_type: 'Card Type',
};

const CATEGORY_LABELS: Record<string, string> = {
    sports_card: 'Sports Card',
    comic_book: 'Comic Book',
    coin: 'Coin',
    stamp: 'Stamp',
    unsupported: 'Unsupported',
};

const showFilters = ref(Object.values(props.filters).some((value) => value !== ''));

function optionLabel(field: FilterField, value: string): string {
    return field === 'category' ? (CATEGORY_LABELS[value] ?? value) : value;
}

function apply(): void {
    router.get(
        '/items',
        {
            q: search.value || undefined,
            sort: sort.value,
            collection: collection.value || undefined,
            ...Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== '')),
        },
        { preserveState: true },
    );
}

function clearFilters(): void {
    filters.value = { category: '', sport: '', year: '', team: '', manufacturer: '', card_type: '' };
    apply();
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

        <div class="mt-3 flex gap-2">
            <select v-model="collection" class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm" @change="apply">
                <option value="">All collections</option>
                <option v-for="entry in collections" :key="entry.id" :value="String(entry.id)">{{ entry.name }}</option>
                <option value="unassigned">Unassigned</option>
            </select>
            <select v-model="sort" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" @change="apply">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="title">By title</option>
            </select>
        </div>

        <button class="mt-3 text-left text-sm font-semibold text-blue-600" @click="showFilters = !showFilters">
            {{ showFilters ? '▾ Hide filters' : '▸ More filters' }}
        </button>

        <div v-if="showFilters" class="mt-2 rounded-xl bg-white p-3 shadow-sm">
            <div class="grid grid-cols-2 gap-2">
                <label v-for="(label, field) in FILTER_LABELS" :key="field" class="text-xs font-medium text-gray-500">
                    {{ label }}
                    <select
                        v-model="filters[field]"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-2 py-2 text-sm text-gray-900"
                        @change="apply"
                    >
                        <option value="">All</option>
                        <option v-for="option in filterOptions[field]" :key="option" :value="option">
                            {{ optionLabel(field, option) }}
                        </option>
                    </select>
                </label>
            </div>
            <button class="mt-3 text-sm font-semibold text-gray-500 hover:text-gray-700" @click="clearFilters">
                Clear filters
            </button>
        </div>

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
                    :src="`/thumbnails/${item.thumbnailImageId}?v=${item.thumbnailVersion}`"
                    :alt="item.title"
                    class="h-20 w-16 rounded-lg bg-gray-100 object-contain"
                />
                <div v-else class="h-20 w-16 rounded-lg bg-gray-200"></div>
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
