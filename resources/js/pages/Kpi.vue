<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    alarms: { id: number; who: string; bagCode: string; at: string }[];
    diagnoseWaiting: number;
    baggers: {
        name: string;
        doneToday: number;
        averageSecondsToday: number;
        doneWeek: number;
        setAsideWeek: number;
        alarmsWeek: number;
    }[];
    stations: {
        name: string;
        type: string;
        revoked: boolean;
        lastSeen: string | null;
        filesToday: number;
        batchesToday: number;
        flagsWeek: number;
    }[];
    flags: { flag: string; total: number; waiting: number }[];
    ledger: { type: string; total: number; inService: number; voided: number; unused: number }[];
    stickerLookup: { code: string; state: string; detail: string } | null;
}>();

const sticker = ref('');

function lookupSticker(): void {
    const value = sticker.value.trim();
    if (!value) return;
    router.get('/kpi', { sticker: value }, { preserveScroll: true, preserveState: true });
}

function seconds(value: number): string {
    return value >= 60 ? `${Math.floor(value / 60)}m ${value % 60}s` : `${value}s`;
}

const TYPE_LABELS: Record<string, string> = { bag: 'Bags', box: 'Boxes', divider: 'Dividers' };
const STATE_STYLES: Record<string, string> = {
    'in service': 'bg-green-50 text-green-700',
    unused: 'bg-gray-100 text-gray-700',
    voided: 'bg-red-50 text-red-700',
    unknown: 'bg-amber-50 text-amber-800',
};
</script>

<template>
    <Head title="KPI Dashboard" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">📟 KPI Dashboard</h1>
            <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">The floor at a glance: people, stations, flags, and every sticker.</p>

        <Link
            v-if="diagnoseWaiting > 0"
            href="/diagnose"
            class="mt-4 block rounded-xl border border-red-200 bg-red-50 p-4 font-semibold text-red-700 hover:bg-red-100"
        >
            🚩 {{ diagnoseWaiting }} flagged bag(s) waiting for diagnosis ›
        </Link>

        <template v-if="alarms.length">
            <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Recent alarms (3 flagged in a row)</p>
            <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                <div v-for="alarm in alarms" :key="alarm.id" class="flex items-center justify-between p-3 text-sm">
                    <p class="font-semibold text-red-700">🚨 {{ alarm.who }}</p>
                    <p class="text-gray-500">{{ alarm.bagCode }} · {{ alarm.at }}</p>
                </div>
            </div>
        </template>

        <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Baggers (7 days)</p>
        <div v-if="baggers.length" class="mt-2 overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                        <th class="p-3">Who</th>
                        <th class="p-3 text-right">Today</th>
                        <th class="p-3 text-right">Avg</th>
                        <th class="p-3 text-right">Week</th>
                        <th class="p-3 text-right">Aside</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="bagger in baggers" :key="bagger.name">
                        <td class="p-3 font-semibold text-gray-900">
                            {{ bagger.name }}
                            <span v-if="bagger.alarmsWeek" class="ml-1 text-xs font-bold text-red-600">🚨×{{ bagger.alarmsWeek }}</span>
                        </td>
                        <td class="p-3 text-right">{{ bagger.doneToday }}</td>
                        <td class="p-3 text-right text-gray-500">{{ bagger.averageSecondsToday ? seconds(bagger.averageSecondsToday) : '—' }}</td>
                        <td class="p-3 text-right">{{ bagger.doneWeek }}</td>
                        <td class="p-3 text-right" :class="bagger.setAsideWeek ? 'font-semibold text-red-600' : 'text-gray-500'">
                            {{ bagger.setAsideWeek }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="mt-2 rounded-xl bg-white p-4 text-sm text-gray-400 shadow-sm">No bagging activity yet this week.</p>

        <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Stations</p>
        <div v-if="stations.length" class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
            <div v-for="station in stations" :key="station.name" class="p-3 text-sm">
                <p class="font-semibold" :class="station.revoked ? 'text-gray-400 line-through' : 'text-gray-900'">
                    {{ station.type === 'cards' ? '🃏' : '📚' }} {{ station.name }}
                    <span v-if="station.flagsWeek" class="ml-1 text-xs font-bold text-red-600">🚩 {{ station.flagsWeek }} this week</span>
                </p>
                <p class="text-xs text-gray-500">
                    {{ station.lastSeen ? `seen ${station.lastSeen}` : 'never connected' }}
                    · {{ station.filesToday }} file(s) today · {{ station.batchesToday }} batch(es) today
                </p>
            </div>
        </div>
        <p v-else class="mt-2 rounded-xl bg-white p-4 text-sm text-gray-400 shadow-sm">No stations registered yet.</p>

        <template v-if="flags.length">
            <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Capture flags (7 days)</p>
            <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                <div v-for="row in flags" :key="row.flag" class="flex items-center justify-between p-3 text-sm">
                    <p class="text-gray-700">🚩 {{ row.flag.replace(/_/g, ' ') }}</p>
                    <p class="font-semibold text-gray-900">
                        {{ row.total }}
                        <span v-if="row.waiting" class="ml-1 text-xs font-bold text-red-600">({{ row.waiting }} waiting)</span>
                    </p>
                </div>
            </div>
        </template>

        <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Sticker ledger</p>
        <div class="mt-2 overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                        <th class="p-3">Type</th>
                        <th class="p-3 text-right">Printed</th>
                        <th class="p-3 text-right">In service</th>
                        <th class="p-3 text-right">Unused</th>
                        <th class="p-3 text-right">Voided</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in ledger" :key="row.type">
                        <td class="p-3 font-semibold text-gray-900">{{ TYPE_LABELS[row.type] ?? row.type }}</td>
                        <td class="p-3 text-right">{{ row.total }}</td>
                        <td class="p-3 text-right text-green-700">{{ row.inService }}</td>
                        <td class="p-3 text-right text-gray-500">{{ row.unused }}</td>
                        <td class="p-3 text-right" :class="row.voided ? 'font-semibold text-red-600' : 'text-gray-400'">{{ row.voided }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-1 text-xs text-gray-400">Printed = in service + unused + voided — always.</p>

        <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Where is this sticker?</p>
        <form class="mt-2 flex gap-2" @submit.prevent="lookupSticker">
            <input
                v-model="sticker"
                type="text"
                placeholder="Scan or type a code…"
                class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2.5 font-mono text-sm"
            />
            <button type="submit" class="shrink-0 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">
                Look up
            </button>
        </form>
        <div v-if="stickerLookup" class="mt-2 rounded-xl p-4 text-sm" :class="STATE_STYLES[stickerLookup.state]">
            <p class="font-mono font-bold">{{ stickerLookup.code }} — {{ stickerLookup.state.toUpperCase() }}</p>
            <p class="mt-1">{{ stickerLookup.detail }}</p>
        </div>
    </div>
</template>
