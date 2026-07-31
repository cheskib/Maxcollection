<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

// The living floor-buildout list: items come from the database; the
// status dropdown flips as gear arrives, and new items can be added
// under any station.
interface Item {
    id: number;
    name: string;
    note: string | null;
    status: string;
    price: string | null;
    links: { label: string; url: string }[];
}

const props = defineProps<{ items: Record<string, Item[]> }>();
const page = usePage<{ flash: { status: string | null } }>();

const STATIONS: { key: string; emoji: string; name: string; photo: string; blurb: string }[] = [
    {
        key: 'comic_photo',
        emoji: '📚',
        name: 'Comic Photo Station',
        photo: '/images/floor/camera-rig.jpg',
        blurb: 'Fixed overhead camera over the six-comic panel — the one station being built from scratch.',
    },
    {
        key: 'card_scan',
        emoji: '🃏',
        name: 'Card Scan Desk',
        photo: '/images/floor/scan-desk.jpg',
        blurb: 'The fi-8170 feeds tickets and cards; the monitor shows status only.',
    },
    {
        key: 'bagging',
        emoji: '🧤',
        name: 'Bagging & Boxing',
        photo: '/images/floor/bagging.jpg',
        blurb: 'Scan in, scan out — the screens are live in this system already.',
    },
    {
        key: 'printing',
        emoji: '🖨️',
        name: 'Printing & Labels',
        photo: '/images/floor/prep.jpg',
        blurb: 'Printers exist — confirm the model before ordering rolls.',
    },
    {
        key: 'everywhere',
        emoji: '🖥️',
        name: 'Everywhere',
        photo: '/images/floor/comic-line.jpg',
        blurb: 'Shared infrastructure across the floor.',
    },
];

const STATUSES: { value: string; label: string; classes: string }[] = [
    { value: 'have', label: '✅ Have', classes: 'bg-green-100 text-green-800' },
    { value: 'ordered', label: '🚚 Ordered', classes: 'bg-blue-100 text-blue-800' },
    { value: 'need', label: '🛒 Need', classes: 'bg-amber-100 text-amber-800' },
    { value: 'later', label: '⏳ Later', classes: 'bg-gray-100 text-gray-600' },
];

function statusClasses(status: string): string {
    return STATUSES.find((entry) => entry.value === status)?.classes ?? 'bg-gray-100 text-gray-600';
}

function setStatus(item: Item, event: Event): void {
    const status = (event.target as HTMLSelectElement).value;
    router.patch(`/equipment/${item.id}`, { status }, { preserveScroll: true });
}

function removeItem(item: Item): void {
    if (!confirm(`Remove "${item.name}" from the list?`)) return;
    router.delete(`/equipment/${item.id}`, { preserveScroll: true });
}

// One add-form open at a time, per station.
const addingTo = ref<string | null>(null);
const newItem = ref({ name: '', note: '', price: '', url: '', status: 'need' });

function openAdd(stationKey: string): void {
    addingTo.value = stationKey;
    newItem.value = { name: '', note: '', price: '', url: '', status: 'need' };
}

function submitAdd(): void {
    if (!addingTo.value || !newItem.value.name.trim()) return;
    router.post(
        '/equipment',
        {
            station: addingTo.value,
            name: newItem.value.name,
            note: newItem.value.note || null,
            price: newItem.value.price || null,
            url: newItem.value.url || null,
            status: newItem.value.status,
        },
        {
            preserveScroll: true,
            onSuccess: () => (addingTo.value = null),
        },
    );
}
</script>

<template>
    <Head title="Equipment" />
    <div class="mx-auto flex min-h-screen w-full max-w-2xl flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">🛒 Equipment</h1>
            <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">
            The floor, station by station. Flip a status when gear arrives; add anything that's missing.
        </p>

        <p v-if="page.props.flash.status" class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">
            ✓ {{ page.props.flash.status }}
        </p>

        <div v-for="station in STATIONS" :key="station.key" class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm">
            <img :src="station.photo" :alt="station.name" class="h-44 w-full object-cover" loading="lazy" />
            <div class="p-4">
                <h2 class="text-lg font-bold text-gray-900">{{ station.emoji }} {{ station.name }}</h2>
                <p class="mt-0.5 text-xs text-gray-500">{{ station.blurb }}</p>

                <div class="mt-3 divide-y divide-gray-100">
                    <div v-for="item in items[station.key] ?? []" :key="item.id" class="flex items-start justify-between gap-3 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900">{{ item.name }}</p>
                            <p v-if="item.note" class="mt-0.5 text-xs text-gray-500">{{ item.note }}</p>
                            <p v-if="item.links.length" class="mt-0.5 space-x-2 text-xs">
                                <a
                                    v-for="link in item.links"
                                    :key="link.url"
                                    :href="link.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="font-semibold text-blue-600 hover:underline"
                                >{{ link.label }} ↗</a>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <p v-if="item.price" class="text-sm font-bold text-gray-900">{{ item.price }}</p>
                            <select
                                :value="item.status"
                                class="rounded-lg border-0 py-1 pl-2 pr-7 text-xs font-bold"
                                :class="statusClasses(item.status)"
                                @change="setStatus(item, $event)"
                            >
                                <option v-for="status in STATUSES" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                            <button
                                class="text-sm font-semibold text-gray-300 hover:text-red-500"
                                title="Remove item"
                                @click="removeItem(item)"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                </div>

                <template v-if="addingTo === station.key">
                    <div class="mt-3 flex flex-col gap-2 rounded-lg bg-gray-50 p-3">
                        <input v-model="newItem.name" type="text" placeholder="Item name" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        <input v-model="newItem.note" type="text" placeholder="Note (optional)" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        <div class="flex gap-2">
                            <input v-model="newItem.price" type="text" placeholder="Price (optional)" class="w-1/3 rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                            <input v-model="newItem.url" type="url" placeholder="Link (optional)" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div class="flex gap-2">
                            <select v-model="newItem.status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option v-for="status in STATUSES" :key="status.value" :value="status.value">{{ status.label }}</option>
                            </select>
                            <button
                                class="flex-1 rounded-lg bg-blue-600 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:bg-gray-300"
                                :disabled="!newItem.name.trim()"
                                @click="submitAdd"
                            >
                                Add Item
                            </button>
                            <button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700" @click="addingTo = null">
                                Cancel
                            </button>
                        </div>
                    </div>
                </template>
                <button
                    v-else
                    class="mt-3 w-full rounded-lg bg-gray-100 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200"
                    @click="openAdd(station.key)"
                >
                    + Add Item
                </button>
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-gray-400">
            Photos are AI-generated visualizations of the planned floor.
        </p>
    </div>
</template>
