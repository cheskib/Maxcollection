<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    confidenceThreshold: number;
    standardModel: string;
    premiumModel: string;
}>();

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
    </div>
</template>
