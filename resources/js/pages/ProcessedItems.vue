<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

interface ProcessedItem {
    id: number;
    thumbnailImageId: number | null;
    thumbnailVersion: string;
    title: string;
    category: string;
    confidence: number | null;
    processedAt: string | null;
    value: { from: number | null; to: number | null; isOurs: boolean };
    keyCard: boolean;
}

function money(from: number | null, to: number | null): string | null {
    if (from === null && to === null) return null;
    const fmt = (v: number) => `$${v.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
    if (from !== null && to !== null) return from === to ? fmt(from) : `${fmt(from)} – ${fmt(to)}`;
    return fmt((from ?? to) as number);
}

type FilterField = 'category' | 'sport' | 'year' | 'team' | 'manufacturer' | 'card_type' | 'publisher' | 'country';

const props = defineProps<{
    items: ProcessedItem[];
    keyOnly: boolean;
    page: { current: number; last: number; total: number };
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
    publisher: 'Publisher',
    country: 'Country',
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

function queryParams(): Record<string, any> {
    return {
        q: search.value || undefined,
        sort: sort.value,
        collection: collection.value || undefined,
        ...Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== '')),
    };
}

// Changing search/sort/filters always restarts at page 1.
function apply(): void {
    router.get('/items', queryParams(), { preserveState: true });
}

function goToPage(page: number): void {
    router.get('/items', { ...queryParams(), page: page > 1 ? page : undefined }, { preserveState: true });
}

function clearFilters(): void {
    filters.value = { category: '', sport: '', year: '', team: '', manufacturer: '', card_type: '', publisher: '', country: '' };
    apply();
}

// ---- Bulk edit: select cards, set fields once, apply to all ----
const page_ = usePage<{ flash: { status: string | null } }>();

const selecting = ref(false);
const selected = ref<number[]>([]);
const applying = ref(false);

const SPORTS = ['Baseball', 'Basketball', 'Football', 'Hockey', 'Soccer', 'Golf', 'Tennis', 'Boxing', 'Wrestling', 'Racing', 'Other'];
const CARD_TYPES = [
    'Base', 'All-Star', 'Team Leaders', 'League Leaders', 'Record Breaker', 'Highlights',
    'Turn Back the Clock', 'Reprint', 'Rookie Subset', 'Future Stars', 'Checklist', 'Traded', 'Insert',
];
const YES_NO = ['Yes', 'No'];

const bulk = ref({ sport: '', team: '', year: '', manufacturer: '', card_type: '', rookie_card: '', autograph: '', collection_id: '' });

function toggleSelect(id: number): void {
    selected.value = selected.value.includes(id) ? selected.value.filter((entry) => entry !== id) : [...selected.value, id];
}

function selectPage(): void {
    selected.value = props.items.map((item) => item.id);
}

function exitSelecting(): void {
    selecting.value = false;
    selected.value = [];
    bulk.value = { sport: '', team: '', year: '', manufacturer: '', card_type: '', rookie_card: '', autograph: '', collection_id: '' };
}

function applyBulk(): void {
    if (selected.value.length === 0) return;
    const { collection_id, ...fields } = bulk.value;
    const filled = Object.fromEntries(Object.entries(fields).filter(([, value]) => value !== ''));

    router.post(
        '/items/bulk-edit',
        { item_ids: selected.value, fields: filled, collection_id: collection_id || undefined },
        {
            onStart: () => (applying.value = true),
            onFinish: () => (applying.value = false),
            onSuccess: () => exitSelecting(),
        },
    );
}
</script>

<template>
    <Head title="Processed Items" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">{{ keyOnly ? '⭐ Key Cards' : 'Processed Items' }}</h1>
            <div class="flex shrink-0 gap-4">
                <button class="text-sm font-semibold text-blue-600" @click="selecting ? exitSelecting() : (selecting = true)">
                    {{ selecting ? 'Cancel' : 'Select' }}
                </button>
                <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
            </div>
        </div>

        <p v-if="page_.props.flash.status" class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">
            ✓ {{ page_.props.flash.status }}
        </p>

        <div v-if="selecting" class="mt-3 rounded-xl border border-blue-200 bg-blue-50 p-4">
            <div class="flex items-center justify-between text-sm">
                <p class="font-semibold text-gray-900">{{ selected.length }} selected</p>
                <div class="flex gap-3">
                    <button class="font-semibold text-blue-600" @click="selectPage">Select page</button>
                    <button class="font-semibold text-gray-500" @click="selected = []">Clear</button>
                </div>
            </div>
            <p class="mt-1 text-xs text-gray-500">Fill only the fields you want to change — blanks are left untouched.</p>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <select v-model="bulk.sport" class="rounded-lg border border-gray-300 px-2 py-2 text-sm">
                    <option value="">Sport — no change</option>
                    <option v-for="sport in SPORTS" :key="sport" :value="sport">{{ sport }}</option>
                </select>
                <select v-model="bulk.card_type" class="rounded-lg border border-gray-300 px-2 py-2 text-sm">
                    <option value="">Card Type — no change</option>
                    <option v-for="cardType in CARD_TYPES" :key="cardType" :value="cardType">{{ cardType }}</option>
                </select>
                <input v-model="bulk.team" type="text" placeholder="Team — no change" class="rounded-lg border border-gray-300 px-2 py-2 text-sm" />
                <input v-model="bulk.year" type="text" placeholder="Year — no change" class="rounded-lg border border-gray-300 px-2 py-2 text-sm" />
                <input v-model="bulk.manufacturer" type="text" placeholder="Manufacturer — no change" class="rounded-lg border border-gray-300 px-2 py-2 text-sm" />
                <select v-model="bulk.collection_id" class="rounded-lg border border-gray-300 px-2 py-2 text-sm">
                    <option value="">Collection — no change</option>
                    <option v-for="entry in collections" :key="entry.id" :value="String(entry.id)">{{ entry.name }}</option>
                    <option value="unassigned">Unassigned</option>
                </select>
                <select v-model="bulk.rookie_card" class="rounded-lg border border-gray-300 px-2 py-2 text-sm">
                    <option value="">Rookie — no change</option>
                    <option v-for="option in YES_NO" :key="option" :value="option">{{ option }}</option>
                </select>
                <select v-model="bulk.autograph" class="rounded-lg border border-gray-300 px-2 py-2 text-sm">
                    <option value="">Autograph — no change</option>
                    <option v-for="option in YES_NO" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <button
                :disabled="applying || selected.length === 0"
                class="mt-3 w-full rounded-lg bg-blue-600 py-2.5 font-semibold text-white hover:bg-blue-700 disabled:bg-gray-300"
                @click="applyBulk"
            >
                {{ applying ? 'Applying…' : `Apply to ${selected.length} item(s)` }}
            </button>
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
                <option value="value">Highest value</option>
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
            <component
                :is="selecting ? 'div' : Link"
                v-for="item in items"
                :key="item.id"
                :href="selecting ? undefined : `/items/${item.id}`"
                class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm"
                :class="selecting && selected.includes(item.id) ? 'ring-2 ring-blue-500' : ''"
                @click="selecting ? toggleSelect(item.id) : undefined"
            >
                <span
                    v-if="selecting"
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 text-xs font-bold"
                    :class="selected.includes(item.id) ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 text-transparent'"
                >
                    ✓
                </span>
                <img
                    v-if="item.thumbnailImageId"
                    :src="`/thumbnails/${item.thumbnailImageId}?v=${item.thumbnailVersion}`"
                    :alt="item.title"
                    class="h-20 w-16 rounded-lg bg-gray-100 object-contain"
                />
                <div v-else class="h-20 w-16 rounded-lg bg-gray-200"></div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-gray-900"><span v-if="item.keyCard">⭐ </span>{{ item.title }}</p>
                    <p class="text-sm text-gray-500">{{ item.category }}</p>
                    <p class="text-xs text-gray-400">
                        <span v-if="item.confidence !== null">{{ Math.round(item.confidence) }}% · </span>{{ item.processedAt }}
                    </p>
                    <p v-if="money(item.value.from, item.value.to)" class="text-sm font-medium text-green-700">
                        {{ money(item.value.from, item.value.to) }}
                        <span v-if="!item.value.isOurs" class="font-normal text-gray-400">(AI)</span>
                    </p>
                </div>
                <span v-if="!selecting" class="text-sm font-semibold text-blue-600">View</span>
            </component>
        </div>

        <div v-if="page.last > 1" class="mt-4 flex items-center justify-between">
            <button
                :disabled="page.current <= 1"
                class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-blue-600 shadow-sm disabled:opacity-40"
                @click="goToPage(page.current - 1)"
            >
                ‹ Prev
            </button>
            <p class="text-xs text-gray-500">Page {{ page.current }} of {{ page.last }} · {{ page.total }} item(s)</p>
            <button
                :disabled="page.current >= page.last"
                class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-blue-600 shadow-sm disabled:opacity-40"
                @click="goToPage(page.current + 1)"
            >
                Next ›
            </button>
        </div>
    </div>
</template>
