<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    batch: {
        id: number;
        bagCode: string;
        bound: boolean;
        flag: string | null;
        station: string | null;
        capturedAt: string;
        resolution: string | null;
    };
    items: { id: number; images: { id: number; role: string | null; version: string }[] }[];
    priorAttempts: {
        id: number;
        resolution: string;
        note: string | null;
        resolvedBy: string | null;
        resolvedAt: string | null;
        flag: string | null;
    }[];
}>();

const page = usePage<{ flash: { status: string | null } }>();
const note = ref('');

function resolve(resolution: 'confirmed' | 'rescan' | 'replaced'): void {
    const messages = {
        confirmed: `Confirm ${props.batch.bagCode} is GOOD? The flag clears and the bag flows normally.`,
        rescan: `DELETE this capture and send ${props.batch.bagCode} back to the scan line? Its items and images are removed; the record of this attempt stays.`,
        replaced: `DELETE this capture and VOID ${props.batch.bagCode} forever? The cards will need a fresh ticket.`,
    };
    if (!confirm(messages[resolution])) return;
    router.post(`/diagnose/${props.batch.id}/resolve`, { resolution, note: note.value || null });
}
</script>

<template>
    <Head :title="`Diagnose ${batch.bagCode}`" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="min-w-0 truncate font-mono text-2xl font-bold text-gray-900">{{ batch.bagCode }}</h1>
            <Link href="/diagnose" class="ml-2 shrink-0 text-sm font-semibold text-blue-600">‹ Diagnose</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">
            Captured {{ batch.capturedAt }}<template v-if="batch.station"> · {{ batch.station }}</template>
            · {{ items.length }} item(s)
        </p>

        <p v-if="page.props.flash.status" class="mt-3 rounded-lg bg-amber-50 p-3 text-sm font-semibold text-amber-800">
            {{ page.props.flash.status }}
        </p>

        <p v-if="batch.flag" class="mt-3 rounded-xl bg-red-50 p-4 text-sm font-bold text-red-700">
            🚩 {{ batch.flag.replace(/_/g, ' ') }}
            <span v-if="!batch.bound" class="mt-1 block text-xs font-normal">
                No bag is bound to this capture — it can only be deleted for rescan or replacement.
            </span>
        </p>
        <p v-else class="mt-3 rounded-xl bg-green-50 p-4 text-sm font-bold text-green-700">Resolved.</p>

        <template v-if="priorAttempts.length">
            <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Earlier attempts (kept on record)</p>
            <div class="mt-2 divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                <div v-for="attempt in priorAttempts" :key="attempt.id" class="p-4 text-sm">
                    <p class="font-semibold text-gray-900">
                        Attempt #{{ attempt.id }} — {{ attempt.resolution }}
                        <span v-if="attempt.flag" class="text-red-600">(was 🚩 {{ attempt.flag.replace(/_/g, ' ') }})</span>
                    </p>
                    <p class="text-xs text-gray-500">
                        <template v-if="attempt.resolvedBy">by {{ attempt.resolvedBy }}</template>
                        <template v-if="attempt.resolvedAt"> · {{ attempt.resolvedAt }}</template>
                        <template v-if="attempt.note"> · “{{ attempt.note }}”</template>
                    </p>
                </div>
            </div>
        </template>

        <!-- The evidence: every captured image, so a bad card can be
             spotted and pulled before the bag rescans. -->
        <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Captured images</p>
        <div class="mt-2 grid grid-cols-4 gap-2">
            <template v-for="item in items" :key="item.id">
                <a
                    v-for="image in item.images"
                    :key="image.id"
                    :href="`/images/${image.id}`"
                    target="_blank"
                    class="relative block overflow-hidden rounded-lg bg-white shadow-sm"
                >
                    <img :src="`/thumbnails/${image.id}?v=${image.version}`" class="aspect-[3/4] w-full object-cover" loading="lazy" />
                    <span v-if="image.role" class="absolute bottom-0 right-0 rounded-tl bg-black/60 px-1 text-[10px] font-semibold text-white">
                        {{ image.role }}
                    </span>
                </a>
            </template>
        </div>

        <template v-if="batch.flag">
            <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Resolution (required — who and when are logged)</p>
            <input
                v-model="note"
                type="text"
                maxlength="500"
                placeholder="Optional note (e.g. pulled 2 damaged cards)"
                class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
            />
            <div class="mt-3 flex flex-col gap-2">
                <button
                    v-if="batch.bound"
                    class="w-full rounded-xl bg-green-600 py-3.5 font-semibold text-white shadow-sm hover:bg-green-700"
                    @click="resolve('confirmed')"
                >
                    ✅ Confirm — the capture is good
                </button>
                <button
                    class="w-full rounded-xl bg-amber-500 py-3.5 font-semibold text-white shadow-sm hover:bg-amber-600"
                    @click="resolve('rescan')"
                >
                    🔁 Delete → send {{ batch.bagCode }} back to the scan line
                </button>
                <button
                    class="w-full rounded-xl bg-red-600 py-3.5 font-semibold text-white shadow-sm hover:bg-red-700"
                    @click="resolve('replaced')"
                >
                    🏷️ Delete → void this bag number, use a fresh ticket
                </button>
            </div>
        </template>
    </div>
</template>
