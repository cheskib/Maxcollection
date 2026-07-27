<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    set: { id: number; name: string; description: string | null };
    cards: {
        itemId: number;
        title: string;
        cardType: string | null;
        thumbnailImageId: number | null;
        thumbnailVersion: string;
    }[];
}>();

const page = usePage<{ flash: { status: string | null } }>();

const editing = ref(false);
const draft = ref(props.set.description ?? '');

function saveDescription(): void {
    router.put(`/sets/${props.set.id}`, { description: draft.value }, {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
}
</script>

<template>
    <Head :title="set.name" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="min-w-0 truncate text-xl font-bold text-gray-900">{{ set.name }}</h1>
            <Link href="/sets" class="ml-3 shrink-0 text-sm font-semibold text-blue-600">All sets</Link>
        </div>

        <p v-if="page.props.flash.status" class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">
            {{ page.props.flash.status }}
        </p>

        <div class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">Design History</h2>
                <button v-if="!editing" class="text-sm font-semibold text-blue-600" @click="editing = true">Edit</button>
            </div>
            <template v-if="editing">
                <textarea
                    v-model="draft"
                    rows="8"
                    class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
                ></textarea>
                <div class="mt-2 flex gap-2">
                    <button class="flex-1 rounded-lg bg-blue-600 py-2 text-sm font-semibold text-white hover:bg-blue-700" @click="saveDescription">
                        Save
                    </button>
                    <button
                        class="flex-1 rounded-lg bg-gray-100 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-200"
                        @click="editing = false; draft = set.description ?? ''"
                    >
                        Cancel
                    </button>
                </div>
            </template>
            <p v-else-if="set.description" class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ set.description }}</p>
            <p v-else class="mt-2 text-sm text-gray-400">
                The write-up is being prepared — it appears shortly after the set's first card is processed. You can also write your own with Edit.
            </p>
        </div>

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Cards you own ({{ cards.length }})</p>
        <div class="mt-2 flex flex-col gap-3">
            <Link
                v-for="card in cards"
                :key="card.itemId"
                :href="`/items/${card.itemId}`"
                class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm hover:bg-gray-50"
            >
                <img
                    v-if="card.thumbnailImageId"
                    :src="`/thumbnails/${card.thumbnailImageId}?v=${card.thumbnailVersion}`"
                    :alt="card.title"
                    class="h-20 w-16 rounded-lg bg-gray-100 object-contain"
                />
                <div v-else class="h-20 w-16 rounded-lg bg-gray-200"></div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-gray-900">{{ card.title }}</p>
                    <p v-if="card.cardType && card.cardType.toLowerCase() !== 'base'" class="text-sm text-gray-500">{{ card.cardType }}</p>
                </div>
                <span class="text-sm font-semibold text-blue-600">View</span>
            </Link>
        </div>
    </div>
</template>
