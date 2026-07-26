<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';

interface Field {
    name: string;
    label: string;
    value: string | null;
}

interface LogLine {
    level: string;
    message: string;
    at: string | null;
}

const props = defineProps<{
    item: {
        id: number;
        status: string;
        reviewReason: string | null;
        title: string;
        category: string;
        confidence: number | null;
        processedAt: string | null;
        images: { id: number; original_filename: string }[];
        fields: Field[];
        processing: { status: string; error: string | null; finishedAt: string | null; logs: LogLine[] } | null;
    };
}>();

const page = usePage<{ flash: { status: string | null } }>();

function reprocess(): void {
    if (!confirm('Reprocess this item with AI? Current metadata will be replaced.')) return;
    router.post(`/items/${props.item.id}/reprocess`);
}

function back(): void {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/items');
    }
}
</script>

<template>
    <Head :title="item.title" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="min-w-0 truncate text-xl font-bold text-gray-900">{{ item.title }}</h1>
            <button class="ml-3 shrink-0 text-sm font-semibold text-blue-600" @click="back">Back</button>
        </div>
        <p class="mt-1 text-sm text-gray-500">
            {{ item.category }}
            <span v-if="item.confidence !== null"> · {{ Math.round(item.confidence) }}% confidence</span>
        </p>

        <p v-if="page.props.flash.status" class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">
            {{ page.props.flash.status }}
        </p>
        <p v-if="item.reviewReason" class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
            Needs review: {{ item.reviewReason }}
        </p>

<!-- object-contain: the full photograph is always visible, never cropped -->
        <div class="mt-4 grid grid-cols-2 gap-3">
            <a v-for="image in item.images" :key="image.id" :href="`/images/${image.id}`" target="_blank">
                <img :src="`/thumbnails/${image.id}`" :alt="image.original_filename" class="w-full rounded-xl bg-gray-100 object-contain shadow-sm" />
            </a>
        </div>

        <div v-if="item.fields.length" class="mt-6 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">Metadata</h2>
            <dl class="mt-2 divide-y divide-gray-100">
                <div v-for="field in item.fields" :key="field.name" class="flex justify-between gap-3 py-2 text-sm">
                    <dt class="text-gray-500">{{ field.label }}</dt>
                    <dd class="text-right font-medium text-gray-900">{{ field.value ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div v-if="item.processing" class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-gray-900">Processing</h2>
            <p class="mt-1 text-sm text-gray-500">
                Status: {{ item.processing.status }}
                <span v-if="item.processing.finishedAt"> · {{ item.processing.finishedAt }}</span>
            </p>
            <p v-if="item.processing.error" class="mt-1 text-sm text-red-600">{{ item.processing.error }}</p>
            <ul class="mt-2 space-y-1 text-xs text-gray-400">
                <li v-for="(log, index) in item.processing.logs" :key="index" :class="{ 'text-red-500': log.level === 'error' }">
                    {{ log.at }} — {{ log.message }}
                </li>
            </ul>
        </div>

        <div class="mt-6 flex flex-col gap-3">
            <Link
                :href="`/items/${item.id}/edit`"
                class="w-full rounded-lg bg-blue-600 py-3 text-center font-semibold text-white hover:bg-blue-700"
            >
                Edit
            </Link>
            <button
                class="w-full rounded-lg bg-gray-200 py-3 font-semibold text-gray-700 hover:bg-gray-300"
                @click="reprocess"
            >
                Reprocess
            </button>
        </div>
    </div>
</template>
