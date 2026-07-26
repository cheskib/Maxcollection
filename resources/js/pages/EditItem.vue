<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    item: {
        id: number;
        title: string;
        category: string;
        values: Record<string, string | null>;
        images: { id: number; original_filename: string }[];
    };
    categoryFields: Record<string, string[]>;
    fieldLabels: Record<string, string>;
}>();

const categories = [
    { value: 'sports_card', label: 'Sports Card' },
    { value: 'comic_book', label: 'Comic Book' },
    { value: 'coin', label: 'Coin' },
    { value: 'stamp', label: 'Stamp' },
    { value: 'unsupported', label: 'Unsupported' },
];

const form = useForm({
    category: props.item.category,
    ...props.item.values,
});

// Only the fields belonging to the selected category are shown and saved.
const visibleFields = computed(() => props.categoryFields[form.category] ?? []);

function submit(): void {
    form.put(`/items/${props.item.id}/metadata`);
}
</script>

<template>
    <Head :title="`Edit ${item.title}`" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="min-w-0 truncate text-xl font-bold text-gray-900">Edit Item</h1>
            <Link :href="`/items/${item.id}`" class="ml-3 shrink-0 text-sm font-semibold text-blue-600">Cancel</Link>
        </div>

<!-- The photographs stay visible while editing: corrections are made by
     reading the item itself. Tapping opens the full-size original. -->
        <div v-if="item.images.length" class="mt-4 grid grid-cols-2 gap-3">
            <a v-for="image in item.images" :key="image.id" :href="`/images/${image.id}`" target="_blank">
                <img
                    :src="`/thumbnails/${image.id}`"
                    :alt="image.original_filename"
                    class="h-44 w-full rounded-xl object-cover shadow-sm"
                />
            </a>
        </div>

        <form class="mt-6 flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                <select
                    id="category"
                    v-model="form.category"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                >
                    <option v-for="category in categories" :key="category.value" :value="category.value">
                        {{ category.label }}
                    </option>
                </select>
            </div>

            <div v-for="field in visibleFields" :key="field">
                <label :for="field" class="block text-sm font-medium text-gray-700">{{ fieldLabels[field] }}</label>
                <textarea
                    v-if="field === 'condition_notes'"
                    :id="field"
                    v-model="(form as Record<string, any>)[field]"
                    rows="3"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                ></textarea>
                <input
                    v-else
                    :id="field"
                    v-model="(form as Record<string, any>)[field]"
                    type="text"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                />
                <p v-if="(form.errors as Record<string, string>)[field]" class="mt-1 text-sm text-red-600">
                    {{ (form.errors as Record<string, string>)[field] }}
                </p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
            >
                Save
            </button>
        </form>
    </div>
</template>
