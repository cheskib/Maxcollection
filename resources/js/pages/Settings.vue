<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    confidenceThreshold: number;
    standardModel: string;
    premiumModel: string;
    marketConfigured: boolean;
    keyNames: Record<string, { id: number; name: string }[]>;
}>();

const SPORTS = ['Baseball', 'Basketball', 'Football', 'Hockey', 'Soccer', 'Golf', 'Tennis', 'Boxing', 'Wrestling', 'Racing', 'Other'];

const newKeySport = ref('Baseball');
const newKeyName = ref('');
const openSport = ref<string | null>(null);

function addKeyName(): void {
    if (!newKeyName.value.trim()) return;
    router.post(
        '/settings/key-names',
        { sport: newKeySport.value, name: newKeyName.value.trim() },
        { preserveScroll: true, onSuccess: () => (newKeyName.value = '') },
    );
}

function removeKeyName(id: number, name: string): void {
    if (!confirm(`Remove "${name}" from the watchlist?`)) return;
    router.delete(`/settings/key-names/${id}`, { preserveScroll: true });
}

// Pricing-data landscape, kept here for reference at any time.
const RESOURCES = {
    api: [
        {
            name: 'PriceCharting / SportsCardsPro',
            url: 'https://www.pricecharting.com',
            note: 'Integrated into this app. Premium subscription unlocks the API token (PRICECHARTING_TOKEN in Railway).',
        },
        {
            name: 'eBay Developer APIs',
            url: 'https://developer.ebay.com',
            note: 'Free account; real SOLD prices need Marketplace Insights approval (application, uncertain timeline). Open API shows asking prices only.',
        },
    ],
    noApi: [
        { name: 'Card Ladder', url: 'https://www.cardladder.com', note: 'Excellent sales history, owned by PSA. App/site only.' },
        { name: 'Beckett', url: 'https://www.beckett.com', note: 'The traditional price guide. Site subscription, no API.' },
        { name: 'Market Movers', url: 'https://www.sportscardinvestor.com', note: 'Analytics tool by Sports Card Investor. No API.' },
        { name: '130point', url: 'https://130point.com', note: 'Free eBay-solds lookup site. No official API.' },
    ],
    partner: [
        { name: 'CollX', url: 'https://www.collx.app', note: 'Card scanning app; data for business partners only.' },
        { name: 'PSA', url: 'https://www.psacard.com', note: 'Grading; cert and auction data for partners only.' },
    ],
};

const page = usePage<{ flash: { status: string | null } }>();

const form = useForm({
    confidence_threshold: props.confidenceThreshold,
});

function save(): void {
    form.post('/settings');
}
</script>

<template>
    <Head title="Settings" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
            <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
        </div>

        <p v-if="page.props.flash.status" class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">
            ✓ {{ page.props.flash.status }}
        </p>

        <form class="mt-4 rounded-xl bg-white p-4 shadow-sm" @submit.prevent="save">
            <h2 class="font-semibold text-gray-900">Review threshold</h2>
            <p class="mt-1 text-sm text-gray-500">
                Cards identified below this confidence go to Needs Review instead of straight into the collection.
            </p>
            <div class="mt-3 flex items-center gap-3">
                <input
                    v-model.number="form.confidence_threshold"
                    type="number"
                    min="0"
                    max="100"
                    class="w-24 rounded-lg border border-gray-300 px-3 py-2.5"
                />
                <span class="text-sm text-gray-500">% confidence</span>
            </div>
            <p v-if="form.errors.confidence_threshold" class="mt-1 text-sm text-red-600">{{ form.errors.confidence_threshold }}</p>
            <button
                type="submit"
                :disabled="form.processing"
                class="mt-4 w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
            >
                Save
            </button>
        </form>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">AI models</h2>
            <dl class="mt-2 divide-y divide-gray-100 text-sm">
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">Standard (batches, reprocess)</dt>
                    <dd class="font-medium text-gray-900">{{ standardModel }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">Premium (★ per card)</dt>
                    <dd class="font-medium text-gray-900">{{ premiumModel }}</dd>
                </div>
            </dl>
            <p class="mt-2 text-xs text-gray-400">Models are configured on the server (Railway variables OPENAI_MODEL / OPENAI_PREMIUM_MODEL).</p>
        </div>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">⭐ Key names watchlist</h2>
            <p class="mt-1 text-sm text-gray-500">
                Cards whose player matches a name here are flagged ⭐ the moment they're processed — regardless of the AI's
                value estimate.
            </p>

            <div class="mt-3 flex gap-2">
                <select v-model="newKeySport" class="rounded-lg border border-gray-300 px-2 py-2 text-sm">
                    <option v-for="sport in SPORTS" :key="sport" :value="sport">{{ sport }}</option>
                </select>
                <input
                    v-model="newKeyName"
                    type="text"
                    placeholder="Add a name…"
                    class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    @keyup.enter="addKeyName"
                />
                <button class="shrink-0 rounded-lg bg-blue-600 px-3 text-sm font-semibold text-white hover:bg-blue-700" @click="addKeyName">
                    Add
                </button>
            </div>

            <div class="mt-3 divide-y divide-gray-100">
                <div v-for="(names, sport) in keyNames" :key="sport" class="py-2">
                    <button
                        class="flex w-full items-center justify-between text-left text-sm font-semibold text-gray-700"
                        @click="openSport = openSport === String(sport) ? null : String(sport)"
                    >
                        <span>{{ sport }}</span>
                        <span class="text-xs text-gray-400">{{ names.length }} name(s) {{ openSport === String(sport) ? '▾' : '▸' }}</span>
                    </button>
                    <div v-if="openSport === String(sport)" class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="entry in names"
                            :key="entry.id"
                            class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700"
                        >
                            {{ entry.name }}
                            <button class="font-bold text-gray-400 hover:text-red-600" @click="removeKeyName(entry.id, entry.name)">✕</button>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">Resources — pricing data sources</h2>

            <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-400">With an API</p>
            <div class="mt-1 divide-y divide-gray-100">
                <div v-for="source in RESOURCES.api" :key="source.name" class="py-2">
                    <div class="flex items-center justify-between gap-2">
                        <a :href="source.url" target="_blank" rel="noopener" class="text-sm font-semibold text-blue-600 hover:underline">
                            {{ source.name }} ↗
                        </a>
                        <span
                            v-if="source.name.startsWith('PriceCharting')"
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="marketConfigured ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                        >
                            {{ marketConfigured ? 'Active' : 'Awaiting token' }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-gray-500">{{ source.note }}</p>
                </div>
            </div>

            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400">No API — consult by hand</p>
            <div class="mt-1 divide-y divide-gray-100">
                <div v-for="source in RESOURCES.noApi" :key="source.name" class="py-2">
                    <a :href="source.url" target="_blank" rel="noopener" class="text-sm font-semibold text-blue-600 hover:underline">
                        {{ source.name }} ↗
                    </a>
                    <p class="mt-0.5 text-xs text-gray-500">{{ source.note }}</p>
                </div>
            </div>

            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400">Partner access only</p>
            <div class="mt-1 divide-y divide-gray-100">
                <div v-for="source in RESOURCES.partner" :key="source.name" class="py-2">
                    <a :href="source.url" target="_blank" rel="noopener" class="text-sm font-semibold text-blue-600 hover:underline">
                        {{ source.name }} ↗
                    </a>
                    <p class="mt-0.5 text-xs text-gray-500">{{ source.note }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
