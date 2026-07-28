<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';

const props = defineProps<{
    openBox: {
        id: number;
        code: string;
        pendingBagCount: number;
        pendingPosition: number | null;
        sections: { position: number; category: string; bagCount: number }[];
    } | null;
    boxes: { id: number; code: string; closedAt: string; bagCount: number; sectionCount: number; cardCount: number }[];
    recentEvents: { id: number; action: string; code: string | null; at: string }[];
}>();

type Scan = { ok: boolean; tone: string; message: string };
const page = usePage<{ flash: { status: string | null; scan: Scan | null } }>();

// Barcode scanners type + Enter into whatever has focus, so the session
// keeps one input focused at all times. "Start Packing Session" is the
// user gesture browsers require before audio is allowed.
const sessionStarted = ref(false);
const scanInput = ref<HTMLInputElement | null>(null);
const code = ref('');
let audio: AudioContext | null = null;

function startSession(): void {
    sessionStarted.value = true;
    audio = new AudioContext();
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

const lastScan = ref<Scan | null>(null);

watch(
    () => page.props.flash.scan,
    (scan) => {
        if (!scan) return;
        lastScan.value = scan;
        beep(scan.tone === 'confirm' ? 'error' : scan.tone);
    },
);

function refocus(): void {
    code.value = '';
    nextTick(() => scanInput.value?.focus());
}

function submitScan(): void {
    const value = code.value.trim();
    if (!value) return;
    router.post('/storage/scan', { code: value }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: refocus,
    });
}

function undo(): void {
    router.post('/storage/undo', {}, { preserveScroll: true, preserveState: true, onFinish: refocus });
}

function completeBox(confirmed = false): void {
    router.post('/storage/complete', { confirmed }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: refocus,
    });
}
</script>

<template>
    <Head title="Storage" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Storage</h1>
            <div class="flex gap-4">
                <Link href="/storage/labels" class="text-sm font-semibold text-blue-600">Print Labels</Link>
                <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
            </div>
        </div>

        <button
            v-if="!sessionStarted"
            class="mt-6 w-full rounded-xl bg-blue-600 py-4 text-lg font-semibold text-white shadow-sm hover:bg-blue-700"
            @click="startSession"
        >
            ▶ Start Packing Session
        </button>

        <template v-else>
<!-- What to scan next — the operator's only instruction -->
            <p class="mt-4 rounded-lg bg-gray-900 p-3 text-center text-sm font-semibold text-white">
                <template v-if="!openBox">Scan a BOX barcode to begin</template>
                <template v-else>
                    Box {{ openBox.code }} · section {{ openBox.pendingPosition }} · scan BAG or divider
                </template>
            </p>

            <input
                ref="scanInput"
                v-model="code"
                type="text"
                autocomplete="off"
                placeholder="Scan barcode…"
                class="mt-3 block w-full rounded-xl border-2 border-blue-400 px-4 py-4 text-center text-lg font-semibold tracking-wider focus:border-blue-600 focus:outline-none"
                @keydown.enter.prevent="submitScan"
            />

<!-- Big visual scan feedback, mirrored by tone -->
            <div
                v-if="lastScan"
                class="mt-3 rounded-xl p-4 text-center text-base font-semibold"
                :class="lastScan.tone === 'success' ? 'bg-green-100 text-green-800'
                    : lastScan.tone === 'info' ? 'bg-blue-100 text-blue-800'
                    : 'bg-red-100 text-red-800'"
            >
                {{ lastScan.tone === 'success' ? '✓' : lastScan.tone === 'info' ? 'ℹ' : '✕' }}
                {{ lastScan.message }}
                <button
                    v-if="lastScan.tone === 'confirm'"
                    class="mt-2 block w-full rounded-lg bg-red-600 py-2 text-sm font-semibold text-white hover:bg-red-700"
                    @click="completeBox(true)"
                >
                    Yes — complete with an unlabeled section
                </button>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-3">
                <button
                    class="rounded-xl bg-amber-100 py-3 text-sm font-semibold text-amber-800 hover:bg-amber-200"
                    @click="undo"
                >
                    ⎌ Undo Last Scan
                </button>
                <button
                    class="rounded-xl py-3 text-sm font-semibold"
                    :class="openBox ? 'bg-gray-900 text-white hover:bg-gray-700' : 'bg-gray-200 text-gray-400'"
                    :disabled="!openBox"
                    @click="completeBox(false)"
                >
                    📦 Box Complete
                </button>
            </div>

<!-- The open box at a glance -->
            <div v-if="openBox" class="mt-4 rounded-xl bg-white p-4 shadow-sm">
                <p class="font-semibold text-gray-900">Box {{ openBox.code }}</p>
                <div class="mt-2 divide-y divide-gray-100">
                    <div
                        v-for="section in openBox.sections"
                        :key="section.position"
                        class="flex items-center justify-between py-2 text-sm"
                    >
                        <p class="text-gray-700">Section {{ section.position }} · {{ section.category }}</p>
                        <span class="font-semibold text-gray-900">{{ section.bagCount }} bag(s)</span>
                    </div>
                    <div class="flex items-center justify-between py-2 text-sm">
                        <p class="text-blue-700">Section {{ openBox.pendingPosition }} · scanning…</p>
                        <span class="font-semibold text-blue-700">{{ openBox.pendingBagCount }} bag(s)</span>
                    </div>
                </div>
            </div>

            <template v-if="recentEvents.length">
                <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Recent activity</p>
                <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                    <div v-for="event in recentEvents" :key="event.id" class="flex items-center justify-between p-3 text-sm">
                        <p class="text-gray-700">{{ event.action }}<span v-if="event.code" class="font-mono text-gray-500"> · {{ event.code }}</span></p>
                        <span class="text-xs text-gray-400">{{ event.at }}</span>
                    </div>
                </div>
            </template>
        </template>

        <template v-if="boxes.length">
            <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Completed boxes</p>
            <div class="mt-2 flex flex-col gap-2">
                <Link
                    v-for="box in boxes"
                    :key="box.id"
                    :href="`/storage/boxes/${box.id}`"
                    class="flex items-center justify-between rounded-xl bg-white p-4 shadow-sm hover:bg-gray-50"
                >
                    <div>
                        <p class="font-semibold text-gray-900">{{ box.code }}</p>
                        <p class="text-xs text-gray-500">Sealed {{ box.closedAt }}</p>
                    </div>
                    <p class="text-sm text-gray-500">{{ box.bagCount }} bags · {{ box.sectionCount }} sections · {{ box.cardCount }} cards</p>
                </Link>
            </div>
        </template>
    </div>
</template>
