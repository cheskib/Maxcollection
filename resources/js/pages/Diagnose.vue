<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { nextTick, onMounted, ref } from 'vue';

defineProps<{
    waiting: { id: number; bagCode: string; flag: string; station: string | null; capturedAt: string; itemCount: number }[];
}>();

const page = usePage<{ flash: { status: string | null } }>();
const scanInput = ref<HTMLInputElement | null>(null);
const code = ref('');

onMounted(() => nextTick(() => scanInput.value?.focus()));

function submitScan(): void {
    const value = code.value.trim();
    code.value = '';
    if (!value) return;
    router.post('/diagnose/scan', { code: value }, { onFinish: () => nextTick(() => scanInput.value?.focus()) });
}
</script>

<template>
    <Head title="Diagnose" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">🔎 Diagnose</h1>
            <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">
            Scan a set-aside bag to review it. Every flagged bag needs a resolution before it moves again.
        </p>

        <input
            ref="scanInput"
            v-model="code"
            type="text"
            autocomplete="off"
            placeholder="Scan the flagged bag…"
            class="mt-4 w-full rounded-xl border-2 border-blue-300 px-4 py-4 text-center font-mono text-lg"
            @keydown.enter.prevent="submitScan"
        />

        <p v-if="page.props.flash.status" class="mt-3 rounded-lg bg-amber-50 p-3 text-sm font-semibold text-amber-800">
            {{ page.props.flash.status }}
        </p>

        <template v-if="waiting.length">
            <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Waiting for diagnosis</p>
            <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                <Link
                    v-for="entry in waiting"
                    :key="entry.id"
                    :href="`/diagnose/${entry.id}`"
                    class="flex items-center justify-between p-4 hover:bg-gray-50"
                >
                    <div>
                        <p class="font-mono font-bold text-gray-900">{{ entry.bagCode }}</p>
                        <p class="text-xs text-gray-500">
                            🚩 {{ entry.flag.replace(/_/g, ' ') }} · {{ entry.itemCount }} item(s)
                            <template v-if="entry.station"> · {{ entry.station }}</template> · {{ entry.capturedAt }}
                        </p>
                    </div>
                    <span class="font-bold text-gray-400">›</span>
                </Link>
            </div>
        </template>
        <p v-else class="mt-8 text-center text-sm text-gray-400">Nothing waiting — the line is clean. ✨</p>
    </div>
</template>
