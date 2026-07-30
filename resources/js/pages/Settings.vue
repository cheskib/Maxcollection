<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    confidenceThreshold: number;
    standardModel: string;
    premiumModel: string;
    marketConfigured: boolean;
    dropbox: {
        configured: boolean;
        connected: boolean;
        connectedAt: string | null;
        archivedCount: number;
        pendingCount: number;
    };
    users: { id: number; name: string; email: string; role: string }[];
    collections: { id: number; name: string }[];
    defaultCollectionId: number | null;
    aiHold: boolean;
    queuedCount: number;
    stations: {
        id: number;
        name: string;
        type: string;
        tokenLast4: string;
        lastSeen: string | null;
        revoked: boolean;
        fileCount: number;
    }[];
    keyNames: Record<string, { id: number; name: string }[]>;
}>();

// Where every scan-line batch lands from now on (forward-only).
const chosenCollection = ref<number | null>(props.defaultCollectionId);

function saveDefaultCollection(): void {
    if (chosenCollection.value === null) return;
    router.post('/settings/default-collection', { collection_id: chosenCollection.value }, { preserveScroll: true });
}

function toggleAiHold(): void {
    const message = props.aiHold
        ? 'Resume AI processing? Everything queued picks back up.'
        : 'Hold ALL AI processing? Scanning and validation continue; queued items wait until you resume.';
    if (!confirm(message)) return;
    router.post('/settings/ai-hold', { hold: !props.aiHold }, { preserveScroll: true });
}

// Scan stations: each station PC runs the uploader agent with its own
// typed token (cards or comics).
const addingStation = ref(false);
const newStation = ref({ name: '', type: 'cards' });

function addStation(): void {
    router.post('/settings/stations', newStation.value, {
        preserveScroll: true,
        onSuccess: () => {
            addingStation.value = false;
            newStation.value = { name: '', type: 'cards' };
        },
    });
}

function revokeStation(station: { id: number; name: string }): void {
    if (!confirm(`Revoke "${station.name}"? Its uploader stops working immediately. Files already received are unaffected.`)) return;
    router.post(`/settings/stations/${station.id}/revoke`, {}, { preserveScroll: true });
}

// Accounts: admins manage the collection; scanners digitize and pack.
const addingUser = ref(false);
const newUser = ref({ name: '', email: '', password: '', role: 'scanner' });

function addUser(): void {
    router.post('/settings/users', newUser.value, {
        preserveScroll: true,
        onSuccess: () => {
            addingUser.value = false;
            newUser.value = { name: '', email: '', password: '', role: 'scanner' };
        },
    });
}

function toggleRole(user: { id: number; name: string; role: string }): void {
    const role = user.role === 'admin' ? 'scanner' : 'admin';
    if (!confirm(`Make ${user.name} a ${role}?`)) return;
    router.post(`/settings/users/${user.id}/role`, { role }, { preserveScroll: true });
}

function disconnectDropbox(): void {
    if (!confirm('Disconnect Dropbox? Archiving stops; copies already in Dropbox stay there.')) return;
    router.post('/settings/dropbox/disconnect', {}, { preserveScroll: true });
}

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
            <h2 class="font-semibold text-gray-900">Export</h2>
            <p class="mt-1 text-sm text-gray-500">
                Download the entire collection — every card, every field, all values — as a CSV that opens directly in
                Excel. Your backup and insurance documentation.
            </p>
            <a
                href="/export"
                class="mt-3 block w-full rounded-lg bg-blue-600 py-3 text-center font-semibold text-white hover:bg-blue-700"
            >
                ⬇ Download CSV Export
            </a>
        </div>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">📥 Scan-line collection</h2>
            <p class="mt-1 text-sm text-gray-500">
                Every batch arriving from the scanners lands in this collection — from the moment you save, forward
                only. Nothing already processed moves.
            </p>
            <div class="mt-2 flex gap-2">
                <select v-model="chosenCollection" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                    <option :value="null" disabled>— choose a collection —</option>
                    <option v-for="collection in collections" :key="collection.id" :value="collection.id">
                        {{ collection.name }}
                    </option>
                </select>
                <button
                    class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                    @click="saveDefaultCollection"
                >
                    Save
                </button>
            </div>
        </div>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">🤖 AI Processing</h2>
            <p class="mt-1 text-sm text-gray-500">
                Scanning and validation always continue — they follow the cards. AI recognition follows the images and
                can be held here whenever you want; queued items simply wait.
            </p>
            <p
                class="mt-2 rounded-lg p-3 text-sm font-semibold"
                :class="aiHold ? 'bg-amber-50 text-amber-800' : 'bg-green-50 text-green-700'"
            >
                {{ aiHold ? `⏸ On hold — ${queuedCount} item(s) waiting.` : '▶ Running normally.' }}
            </p>
            <button
                class="mt-2 w-full rounded-lg py-2.5 text-sm font-semibold"
                :class="aiHold ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-amber-100 text-amber-800 hover:bg-amber-200'"
                @click="toggleAiHold"
            >
                {{ aiHold ? '▶ Resume AI Processing' : '⏸ Hold AI Processing' }}
            </button>
        </div>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">🖥️ Scan Stations</h2>
            <p class="mt-1 text-sm text-gray-500">
                Each station PC runs the uploader agent. Register the station here, then on that PC: download the
                agent and its config into <span class="font-mono text-xs">C:\MaxCollection\</span> and run it once.
            </p>

            <div v-if="stations.length" class="mt-2 divide-y divide-gray-100">
                <div v-for="station in stations" :key="station.id" class="flex items-center justify-between py-2.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold" :class="station.revoked ? 'text-gray-400 line-through' : 'text-gray-900'">
                            {{ station.name }}
                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">
                                {{ station.type === 'cards' ? '🃏 Cards' : '📚 Comics' }}
                            </span>
                        </p>
                        <p class="text-xs text-gray-500">
                            Token …{{ station.tokenLast4 }}
                            · {{ station.lastSeen ? `last seen ${station.lastSeen}` : 'never connected' }}
                            · {{ station.fileCount }} file(s)
                        </p>
                    </div>
                    <div v-if="!station.revoked" class="ml-3 flex shrink-0 gap-3">
                        <a :href="`/settings/stations/${station.id}/config`" class="text-sm font-semibold text-blue-600">Config</a>
                        <button class="text-sm font-semibold text-red-500 hover:text-red-700" @click="revokeStation(station)">
                            Revoke
                        </button>
                    </div>
                </div>
            </div>

            <template v-if="addingStation">
                <div class="mt-3 flex flex-col gap-2">
                    <input
                        v-model="newStation.name"
                        type="text"
                        placeholder="Station name (e.g. Card scan desk 1)"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    />
                    <select v-model="newStation.type" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="cards">🃏 Cards — scan desk</option>
                        <option value="comics">📚 Comics — photo station</option>
                    </select>
                    <div class="flex gap-2">
                        <button
                            class="flex-1 rounded-lg bg-blue-600 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:bg-gray-300"
                            :disabled="!newStation.name.trim()"
                            @click="addStation"
                        >
                            Register Station
                        </button>
                        <button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700" @click="addingStation = false">
                            Cancel
                        </button>
                    </div>
                </div>
            </template>
            <button
                v-else
                class="mt-3 w-full rounded-lg bg-gray-100 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200"
                @click="addingStation = true"
            >
                + Register Station
            </button>

            <a
                href="/downloads/maxcollection-uploader.exe"
                class="mt-2 block w-full rounded-lg bg-gray-100 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-200"
            >
                ⬇ Download Uploader Agent (Windows)
            </a>
        </div>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">👥 Accounts</h2>
            <p class="mt-1 text-sm text-gray-500">
                Admins manage the collection — removals, reports, settings, deletions. Scanners digitize and pack only.
            </p>
            <div class="mt-2 divide-y divide-gray-100">
                <div v-for="user in users" :key="user.id" class="flex items-center justify-between py-2 text-sm">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-gray-900">{{ user.name }}</p>
                        <p class="truncate text-xs text-gray-400">{{ user.email }}</p>
                    </div>
                    <button
                        class="ml-2 shrink-0 rounded-full px-2.5 py-1 text-xs font-bold"
                        :class="user.role === 'admin' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'"
                        title="Tap to switch role"
                        @click="toggleRole(user)"
                    >
                        {{ user.role }}
                    </button>
                </div>
            </div>
            <button
                v-if="!addingUser"
                class="mt-2 text-sm font-semibold text-blue-600 hover:text-blue-700"
                @click="addingUser = true"
            >
                ＋ Add account
            </button>
            <div v-else class="mt-2 flex flex-col gap-2">
                <input v-model="newUser.name" type="text" placeholder="Name" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                <input v-model="newUser.email" type="email" placeholder="Email" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                <input v-model="newUser.password" type="password" placeholder="Password (8+ characters)" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                <select v-model="newUser.role" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                    <option value="scanner">Scanner — digitize and pack only</option>
                    <option value="admin">Admin — full control</option>
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <button class="rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" @click="addUser">
                        Create Account
                    </button>
                    <button class="rounded-lg bg-gray-100 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-200" @click="addingUser = false">
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">☁️ Dropbox Archive</h2>
            <p class="mt-1 text-sm text-gray-500">
                Every finalized bag's original photos are copied to your Dropbox under its bag number — the off-site
                safety net for the whole collection.
            </p>

            <p v-if="!dropbox.configured" class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-700">
                Add DROPBOX_APP_KEY and DROPBOX_APP_SECRET in Railway to enable connecting.
            </p>

            <a
                v-else-if="!dropbox.connected"
                href="/settings/dropbox/connect"
                class="mt-3 block w-full rounded-lg bg-blue-600 py-3 text-center font-semibold text-white hover:bg-blue-700"
            >
                🔗 Connect Dropbox
            </a>

            <template v-else>
                <p class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">
                    ✓ Connected<span v-if="dropbox.connectedAt"> since {{ dropbox.connectedAt }}</span> ·
                    {{ dropbox.archivedCount }} batch(es) archived<span v-if="dropbox.pendingCount"> ·
                    <span class="font-semibold text-amber-700">{{ dropbox.pendingCount }} pending</span></span>
                </p>
                <button
                    v-if="dropbox.pendingCount"
                    class="mt-2 w-full rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                    @click="router.post('/settings/dropbox/archive-pending', {}, { preserveScroll: true })"
                >
                    ☁️ Archive {{ dropbox.pendingCount }} pending batch(es)
                </button>
                <button class="mt-2 w-full text-sm text-gray-400 hover:text-gray-600" @click="disconnectDropbox">
                    Disconnect
                </button>
            </template>
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
