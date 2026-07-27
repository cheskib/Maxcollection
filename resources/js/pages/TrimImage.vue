<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    image: {
        id: number;
        itemId: number;
        version: string;
        crop: { top: number; right: number; bottom: number; left: number };
    };
}>();

const top = ref(props.image.crop.top);
const right = ref(props.image.crop.right);
const bottom = ref(props.image.crop.bottom);
const left = ref(props.image.crop.left);
const saving = ref(false);

function save(): void {
    saving.value = true;
    router.post(
        `/images/${props.image.id}/trim`,
        { top: top.value, right: right.value, bottom: bottom.value, left: left.value },
        { onFinish: () => (saving.value = false) },
    );
}

function reset(): void {
    top.value = 0;
    right.value = 0;
    bottom.value = 0;
    left.value = 0;
}
</script>

<template>
    <Head title="Trim Photo" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Trim Photo</h1>
            <Link :href="`/items/${image.itemId}`" class="text-sm font-semibold text-blue-600">Cancel</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">Shaded edges will be trimmed away. The original photo is kept safe.</p>

        <div class="relative mt-4 overflow-hidden rounded-xl bg-gray-100 shadow-sm">
            <img :src="`/images/${image.id}?uncropped=1&v=${image.version}`" alt="Photo to trim" class="w-full" />
            <div class="absolute inset-x-0 top-0 bg-gray-900/60" :style="{ height: `${top}%` }"></div>
            <div class="absolute inset-x-0 bottom-0 bg-gray-900/60" :style="{ height: `${bottom}%` }"></div>
            <div class="absolute left-0 bg-gray-900/60" :style="{ top: `${top}%`, bottom: `${bottom}%`, width: `${left}%` }"></div>
            <div class="absolute right-0 bg-gray-900/60" :style="{ top: `${top}%`, bottom: `${bottom}%`, width: `${right}%` }"></div>
        </div>

        <div class="mt-4 flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm">
            <label class="text-sm font-medium text-gray-700">
                Top · {{ top }}%
                <input v-model.number="top" type="range" min="0" max="45" class="mt-1 w-full" />
            </label>
            <label class="text-sm font-medium text-gray-700">
                Bottom · {{ bottom }}%
                <input v-model.number="bottom" type="range" min="0" max="45" class="mt-1 w-full" />
            </label>
            <label class="text-sm font-medium text-gray-700">
                Left · {{ left }}%
                <input v-model.number="left" type="range" min="0" max="45" class="mt-1 w-full" />
            </label>
            <label class="text-sm font-medium text-gray-700">
                Right · {{ right }}%
                <input v-model.number="right" type="range" min="0" max="45" class="mt-1 w-full" />
            </label>
        </div>

        <div class="mt-4 flex flex-col gap-3">
            <button
                :disabled="saving"
                class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                @click="save"
            >
                Save Trim
            </button>
            <button class="w-full rounded-lg bg-gray-200 py-3 font-semibold text-gray-700 hover:bg-gray-300" @click="reset">
                Reset (no trim)
            </button>
        </div>
    </div>
</template>
