<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    counts: { bag: number; box: number; divider: number };
}>();

const form = useForm<{ type: string; count: number; names: string }>({
    type: 'bag',
    count: 30,
    names: '',
});

function submit(): void {
    form.post('/storage/labels');
}
</script>

<template>
    <Head title="Print Labels" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Print Labels</h1>
            <Link href="/storage" class="text-sm font-semibold text-blue-600">Storage</Link>
        </div>
        <p class="mt-2 text-sm text-gray-500">
            Every generated barcode is registered before it is printed, so the system always knows every label that
            exists — a scan of an unknown code is instantly a misread. Registered so far:
            {{ counts.bag }} bags, {{ counts.box }} boxes, {{ counts.divider }} dividers.
        </p>

        <form class="mt-6 flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700">Label type</label>
                <select id="type" v-model="form.type" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5">
                    <option value="bag">Bag labels (BAG-…)</option>
                    <option value="box">Box labels (BOX-…)</option>
                    <option value="divider">Divider card labels (DIV-…)</option>
                </select>
            </div>

            <div v-if="form.type !== 'divider'">
                <label for="count" class="block text-sm font-medium text-gray-700">How many</label>
                <input
                    id="count"
                    v-model.number="form.count"
                    type="number"
                    min="1"
                    max="200"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                />
                <p v-if="form.errors.count" class="mt-1 text-sm text-red-600">{{ form.errors.count }}</p>
            </div>

            <div v-else>
                <label for="names" class="block text-sm font-medium text-gray-700">Divider names — one per line</label>
                <textarea
                    id="names"
                    v-model="form.names"
                    rows="6"
                    placeholder="Baseball 80s&#10;Football Stars&#10;Mixed"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                ></textarea>
                <p class="mt-1 text-xs text-gray-400">Names are printed on the labels; the barcode itself stays a neutral CAT number.</p>
                <p v-if="form.errors.names" class="mt-1 text-sm text-red-600">{{ form.errors.names }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
            >
                Generate &amp; Open Print Sheet
            </button>
        </form>
    </div>
</template>
