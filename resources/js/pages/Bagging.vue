<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

const props = defineProps<{
    open: { bagCode: string | null; verdict: string | null; flagReason: string | null; startedAt: string } | null;
    today: { done: number; setAside: number; averageSeconds: number };
    recent: { id: number; bagCode: string; action: string; seconds: number | null; at: string }[];
}>();

const page = usePage<{ flash: { scan: { ok: boolean; tone: string; message: string; alarm: boolean } | null } }>();

const sessionStarted = ref(false);
const scanInput = ref<HTMLInputElement | null>(null);
const code = ref('');
let audio: AudioContext | null = null;

// Sounds are load-bearing (owner ruling): the session opens with a
// sound check so a dead speaker is discovered before the first bin.
function startSession(): void {
    sessionStarted.value = true;
    audio = new AudioContext();
    beep('success');
    setTimeout(() => beep('error'), 700);
    nextTick(() => scanInput.value?.focus());
}

function beep(tone: string): void {
    if (!audio) return;
    const osc = audio.createOscillator();
    const gain = audio.createGain();
    osc.connect(gain);
    gain.connect(audio.destination);
    osc.type = tone === 'error' ? 'square' : 'sine';
    osc.frequency.value = tone === 'success' ? 880 : tone === 'error' ? 200 : 520;
    gain.gain.value = 0.15;
    osc.start();
    osc.stop(audio.currentTime + (tone === 'error' ? 0.35 : 0.12));
}

function submitScan(): void {
    const value = code.value.trim();
    code.value = '';
    if (!value) return;

    router.post('/bagging/scan', { code: value }, {
        preserveScroll: true,
        onSuccess: () => {
            const result = page.props.flash.scan;
            if (result) beep(result.tone);
            if (result?.alarm) {
                // The 3-in-a-row alarm: unmistakable.
                setTimeout(() => beep('error'), 450);
                setTimeout(() => beep('error'), 900);
            }
            nextTick(() => scanInput.value?.focus());
        },
    });
}

// Live elapsed time on the open bag.
const nowTick = ref(Date.now());
const clock = setInterval(() => (nowTick.value = Date.now()), 1000);
onBeforeUnmount(() => clearInterval(clock));

const elapsed = computed(() => {
    if (!props.open) return '';
    const seconds = Math.max(0, Math.floor((nowTick.value - new Date(props.open.startedAt).getTime()) / 1000));
    return seconds >= 60 ? `${Math.floor(seconds / 60)}m ${seconds % 60}s` : `${seconds}s`;
});

function avg(seconds: number): string {
    return seconds >= 60 ? `${Math.floor(seconds / 60)}m ${seconds % 60}s` : `${seconds}s`;
}
</script>

<template>
    <Head title="Bagging" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">🧤 Bagging</h1>
            <div class="flex gap-4">
                <Link href="/bagging/set-aside-card" class="text-sm font-semibold text-blue-600">SET-ASIDE card</Link>
                <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
            </div>
        </div>
        <p class="mt-1 text-sm text-gray-500">
            Scan the bin's ticket → verdict → peel, fill, seal → scan the bag again. Flagged bins go aside.
        </p>

        <template v-if="!sessionStarted">
            <button
                class="mt-8 w-full rounded-xl bg-blue-600 py-5 text-lg font-bold text-white shadow-sm hover:bg-blue-700"
                @click="startSession"
            >
                ▶ Start Bagging (sound check)
            </button>
            <p class="mt-2 text-center text-xs text-gray-400">You'll hear the good tone, then the problem tone.</p>
        </template>

        <template v-else>
            <input
                ref="scanInput"
                v-model="code"
                type="text"
                autocomplete="off"
                placeholder="Scan…"
                class="mt-4 w-full rounded-xl border-2 border-blue-300 px-4 py-4 text-center font-mono text-lg"
                @keydown.enter.prevent="submitScan"
            />

            <p
                v-if="page.props.flash.scan"
                class="mt-3 rounded-xl p-4 text-center text-base font-bold"
                :class="{
                    'bg-green-50 text-green-700': page.props.flash.scan.tone === 'success',
                    'bg-red-50 text-red-700': page.props.flash.scan.tone === 'error',
                    'bg-amber-50 text-amber-800': page.props.flash.scan.tone === 'neutral',
                }"
            >
                {{ page.props.flash.scan.message }}
            </p>

            <p
                v-if="page.props.flash.scan?.alarm"
                class="mt-2 animate-pulse rounded-xl bg-red-600 p-4 text-center text-base font-bold text-white"
            >
                🚨 3 FLAGGED BAGS IN A ROW — admin has been alerted. Pause and check the line.
            </p>

            <div v-if="open" class="mt-4 rounded-xl border-2 p-4" :class="open.verdict === 'flagged' ? 'border-red-300 bg-red-50' : 'border-green-300 bg-green-50'">
                <p class="text-xs font-semibold uppercase tracking-wide" :class="open.verdict === 'flagged' ? 'text-red-500' : 'text-green-600'">
                    {{ open.verdict === 'flagged' ? 'Flagged — set aside' : 'In progress' }}
                </p>
                <p class="mt-1 font-mono text-2xl font-bold text-gray-900">{{ open.bagCode }}</p>
                <p class="mt-1 text-sm" :class="open.verdict === 'flagged' ? 'text-red-700' : 'text-green-700'">
                    <template v-if="open.verdict === 'flagged'">
                        {{ (open.flagReason ?? '').replace(/_/g, ' ') }} — scan the SET-ASIDE card to continue.
                    </template>
                    <template v-else>Peel · fill · seal — then scan the bag again. ⏱ {{ elapsed }}</template>
                </p>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-white p-3 shadow-sm">
                    <p class="text-2xl font-bold text-gray-900">{{ today.done }}</p>
                    <p class="text-xs text-gray-500">bags today</p>
                </div>
                <div class="rounded-xl bg-white p-3 shadow-sm">
                    <p class="text-2xl font-bold" :class="today.setAside > 0 ? 'text-red-600' : 'text-gray-900'">{{ today.setAside }}</p>
                    <p class="text-xs text-gray-500">set aside</p>
                </div>
                <div class="rounded-xl bg-white p-3 shadow-sm">
                    <p class="text-2xl font-bold text-gray-900">{{ today.averageSeconds ? avg(today.averageSeconds) : '—' }}</p>
                    <p class="text-xs text-gray-500">avg per bag</p>
                </div>
            </div>

            <template v-if="recent.length">
                <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Recent</p>
                <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                    <div v-for="event in recent" :key="event.id" class="flex items-center justify-between px-4 py-2.5 text-sm">
                        <p class="font-mono font-semibold text-gray-900">{{ event.bagCode }}</p>
                        <p :class="event.action === 'set_aside' ? 'font-semibold text-red-600' : 'text-gray-500'">
                            {{ event.action === 'set_aside' ? 'set aside' : 'done' }}
                            <span v-if="event.seconds !== null"> · {{ avg(event.seconds) }}</span>
                            · {{ event.at }}
                        </p>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>
