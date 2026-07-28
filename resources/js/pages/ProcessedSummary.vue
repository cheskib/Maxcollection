<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    category: string | null;
    categoryLabel: string | null;
    sport: string | null;
    collection: string | null;
    collectionName: string | null;
    value: { from: number | null; to: number | null };
    total: number;
    categories: { value: string; label: string; count: number }[];
    groupField: string | null;
    groups: { value: string; count: number }[];
    cardTypes: { value: string; count: number }[];
    collections: {
        named: { id: number; name: string; count: number }[];
        unassigned: number;
    };
    sets: { id: number; name: string; count: number }[];
}>();

const search = ref('');

function goSearch(): void {
    router.get('/items', search.value ? { q: search.value } : {});
}

// The pre-filtered Processed Items list for the current drill-down level.
function listUrl(extra: Record<string, string> = {}): string {
    const params = new URLSearchParams();
    if (props.collection) params.set('collection', props.collection);
    if (props.category) params.set('category', props.category);
    if (props.sport) params.set('sport', props.sport);
    for (const [key, value] of Object.entries(extra)) params.set(key, value);
    const query = params.toString();
    return query ? `/items?${query}` : '/items';
}

// A summary URL one level up or down, keeping the collection scope.
function summaryUrl(extra: Record<string, string> = {}): string {
    const params = new URLSearchParams();
    if (props.collection) params.set('collection', props.collection);
    for (const [key, value] of Object.entries(extra)) params.set(key, value);
    const query = params.toString();
    return query ? `/items/summary?${query}` : '/items/summary';
}

function money(from: number | null, to: number | null): string | null {
    if (from === null && to === null) return null;
    const fmt = (v: number) => `$${v.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
    if (from !== null && to !== null) return from === to ? fmt(from) : `${fmt(from)} – ${fmt(to)}`;
    return fmt((from ?? to) as number);
}
</script>

<template>
    <Head title="Processed Items" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="min-w-0 truncate text-2xl font-bold text-gray-900">
                {{ sport ?? categoryLabel ?? collectionName ?? 'Processed Items' }}
            </h1>
            <div class="ml-3 flex shrink-0 gap-4">
                <Link
                    v-if="sport && category"
                    :href="summaryUrl({ category })"
                    class="text-sm font-semibold text-blue-600"
                >
                    ‹ {{ categoryLabel }}
                </Link>
                <Link v-else-if="category" :href="summaryUrl()" class="text-sm font-semibold text-blue-600">‹ Categories</Link>
                <Link v-else-if="collection" href="/collections" class="text-sm font-semibold text-blue-600">‹ Collections</Link>
                <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
            </div>
        </div>
        <p class="mt-1 text-sm text-gray-500">
            {{ total }} item(s)<template v-if="!category"> identified — pick a category</template>.
            <span v-if="money(value.from, value.to)" class="font-semibold text-green-700">{{ money(value.from, value.to) }}</span>
        </p>

        <form v-if="!category" class="mt-4 flex gap-2" @submit.prevent="goSearch">
            <input
                v-model="search"
                type="search"
                placeholder="Search players, sets, years…"
                class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
            />
            <button type="submit" class="shrink-0 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">
                Search
            </button>
        </form>

        <!-- Level 1: pick a category -->
        <template v-if="!category">
            <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">By category</p>
            <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                <Link
                    v-for="row in categories"
                    :key="row.value"
                    :href="summaryUrl({ category: row.value })"
                    class="flex items-center justify-between p-4 hover:bg-gray-50"
                >
                    <p class="text-sm text-gray-700">{{ row.label }}</p>
                    <span class="font-bold text-gray-900">{{ row.count }} ›</span>
                </Link>
            </div>

            <template v-if="collections.named.length || collections.unassigned > 0">
                <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">By collection</p>
                <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                    <Link
                        v-for="row in collections.named"
                        :key="row.id"
                        :href="`/items/summary?collection=${row.id}`"
                        class="flex items-center justify-between p-4 hover:bg-gray-50"
                    >
                        <p class="text-sm text-gray-700">{{ row.name }}</p>
                        <span class="font-bold text-gray-900">{{ row.count }} ›</span>
                    </Link>
                    <Link
                        v-if="collections.unassigned > 0"
                        href="/items/summary?collection=unassigned"
                        class="flex items-center justify-between p-4 hover:bg-gray-50"
                    >
                        <p class="text-sm text-gray-500">Unassigned</p>
                        <span class="font-bold text-gray-900">{{ collections.unassigned }} ›</span>
                    </Link>
                </div>
            </template>

            <template v-if="sets.length">
                <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">By set</p>
                <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                    <Link
                        v-for="set in sets"
                        :key="set.id"
                        :href="`/sets/${set.id}`"
                        class="flex items-center justify-between p-4 hover:bg-gray-50"
                    >
                        <p class="text-sm text-gray-700">{{ set.name }}</p>
                        <span class="font-bold text-gray-900">{{ set.count }} ›</span>
                    </Link>
                </div>
            </template>
        </template>

        <!-- Level 2: a category is chosen; break down by its natural group
             (sport, publisher, or country). Sports drill one level deeper;
             the others open their filtered list directly. -->
        <template v-else-if="!sport">
            <template v-if="groups.length && groupField">
                <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">By {{ groupField.replace('_', ' ') }}</p>
                <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                    <Link
                        v-for="row in groups"
                        :key="row.value"
                        :href="
                            groupField === 'sport'
                                ? summaryUrl({ category, sport: row.value })
                                : listUrl({ [groupField]: row.value })
                        "
                        class="flex items-center justify-between p-4 hover:bg-gray-50"
                    >
                        <p class="text-sm text-gray-700">{{ row.value }}</p>
                        <span class="font-bold text-gray-900">{{ row.count }} ›</span>
                    </Link>
                </div>
            </template>
            <Link
                :href="listUrl()"
                class="mt-6 block rounded-xl bg-blue-600 p-4 text-center font-semibold text-white shadow-sm hover:bg-blue-700"
            >
                View all {{ total }} item(s)
            </Link>
        </template>

        <!-- Level 3: a sport is chosen; break down by card type -->
        <template v-else>
            <template v-if="cardTypes.length">
                <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">By card type</p>
                <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                    <Link
                        v-for="row in cardTypes"
                        :key="row.value"
                        :href="listUrl({ card_type: row.value })"
                        class="flex items-center justify-between p-4 hover:bg-gray-50"
                    >
                        <p class="text-sm text-gray-700">{{ row.value }}</p>
                        <span class="font-bold text-gray-900">{{ row.count }} ›</span>
                    </Link>
                </div>
            </template>
            <Link
                :href="listUrl()"
                class="mt-6 block rounded-xl bg-blue-600 p-4 text-center font-semibold text-white shadow-sm hover:bg-blue-700"
            >
                View all {{ total }} item(s)
            </Link>
        </template>
    </div>
</template>
