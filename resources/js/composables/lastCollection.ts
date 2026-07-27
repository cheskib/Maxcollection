import type { CollectionChoice } from '../components/CollectionPicker.vue';

// The capture screens default to whatever collection was used last.
const KEY = 'maxcollection.lastCollectionId';

export function loadLastCollection(collections: { id: number; name: string }[]): CollectionChoice {
    const raw = localStorage.getItem(KEY);
    const id = raw === null ? null : Number(raw);
    const valid = id !== null && collections.some((collection) => collection.id === id);
    return { collectionId: valid ? id : null, newName: '' };
}

export function saveLastCollection(id: number | null): void {
    if (id === null) {
        localStorage.removeItem(KEY);
    } else {
        localStorage.setItem(KEY, String(id));
    }
}

export function collectionPayload(choice: CollectionChoice): Record<string, string> {
    return {
        collection_id: choice.collectionId === 'new' || choice.collectionId === null ? '' : String(choice.collectionId),
        new_collection_name: choice.collectionId === 'new' ? choice.newName : '',
    };
}
