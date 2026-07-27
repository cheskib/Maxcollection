<script setup lang="ts">
// Shared picker: choose an existing collection, none, or type a new name.
export interface CollectionChoice {
    collectionId: number | null | 'new';
    newName: string;
}

defineProps<{
    collections: { id: number; name: string }[];
}>();

const choice = defineModel<CollectionChoice>({ required: true });
</script>

<template>
    <div class="flex flex-col gap-2">
        <select
            :value="choice.collectionId === null ? '' : String(choice.collectionId)"
            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5"
            @change="
                choice = {
                    collectionId:
                        ($event.target as HTMLSelectElement).value === ''
                            ? null
                            : ($event.target as HTMLSelectElement).value === 'new'
                              ? 'new'
                              : Number(($event.target as HTMLSelectElement).value),
                    newName: choice.newName,
                }
            "
        >
            <option value="">— Unassigned —</option>
            <option v-for="collection in collections" :key="collection.id" :value="String(collection.id)">
                {{ collection.name }}
            </option>
            <option value="new">➕ New collection…</option>
        </select>
        <input
            v-if="choice.collectionId === 'new'"
            :value="choice.newName"
            type="text"
            placeholder="New collection name"
            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5"
            @input="choice = { collectionId: 'new', newName: ($event.target as HTMLInputElement).value }"
        />
    </div>
</template>
