<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    level: 'sport' | 'year' | 'team' | 'players';
    filters: { sport: string | null; year: string | null; team: string | null };
    groups: { label: string; count: number }[];
    items: {
        id: number;
        thumbnailImageId: number | null;
        thumbnailRotation: number;
        player: string;
        title: string;
        confidence: number | null;
    }[];
}>();

const heading = computed(() => {
    if (props.level === 'sport') return 'Browse by Sport';
    if (props.level === 'year') return props.filters.sport ?? '';
    if (props.level === 'team') return `${props.filters.sport} · ${props.filters.year}`;
    return `${props.filters.team} · ${props.filters.year}`;
});

function drill(label: string): void {
    const query: Record<string, string> = {};
    if (props.filters.sport) query.sport = props.filters.sport;
    if (props.filters.year) query.year = props.filters.year;
    if (props.level === 'sport') query.sport = label;
    if (props.level === 'year') query.year = label;
    if (props.level === 'team') query.team = label;
    router.get('/browse', query);
}

// Breadcrumb: each crumb links back up to its level.
const crumbs = computed(() => {
    const href = (query: Record<string, string>) => `/browse?${new URLSearchParams(query).toString()}`;
    const list: { label: string; href: string }[] = [{ label: 'Sports', href: href({}) }];
    if (props.filters.sport) list.push({ label: props.filters.sport, href: href({ sport: props.filters.sport }) });
    if (props.filters.year) list.push({ label: props.filters.year, href: href({ sport: props.filters.sport!, year: props.filters.year }) });
    if (props.filters.team) {
        list.push({
            label: props.filters.team,
            href: href({ sport: props.filters.sport!, year: props.filters.year!, team: props.filters.team }),
        });
    }
    return list;
});
</script>

<template>
    <Head title="Browse" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="min-w-0 truncate text-2xl font-bold text-gray-900">{{ heading }}</h1>
            <Link href="/" class="ml-2 shrink-0 text-sm font-semibold text-blue-600">Home</Link>
        </div>

        <nav class="mt-2 flex flex-wrap items-center gap-1 text-sm text-gray-500">
            <template v-for="(crumb, index) in crumbs" :key="crumb.label">
                <span v-if="index > 0">›</span>
                <Link v-if="index < crumbs.length - 1" :href="crumb.href" class="text-blue-600">
                    {{ crumb.label }}
                </Link>
                <span v-else class="font-medium text-gray-700">{{ crumb.label }}</span>
            </template>
        </nav>

        <template v-if="level !== 'players'">
            <p v-if="groups.length === 0" class="mt-10 text-center text-gray-500">Nothing here yet.</p>
            <div class="mt-4 flex flex-col gap-2">
                <button
                    v-for="group in groups"
                    :key="group.label"
                    class="flex items-center justify-between rounded-xl bg-white p-4 text-left shadow-sm hover:bg-gray-50"
                    @click="drill(group.label)"
                >
                    <span class="font-semibold text-gray-900">{{ group.label }}</span>
                    <span class="text-sm text-gray-500">{{ group.count }} card(s) ›</span>
                </button>
            </div>
        </template>

        <template v-else>
            <div class="mt-4 flex flex-col gap-3">
                <Link
                    v-for="item in items"
                    :key="item.id"
                    :href="`/items/${item.id}`"
                    class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm"
                >
                    <img
                        v-if="item.thumbnailImageId"
                        :src="`/thumbnails/${item.thumbnailImageId}?v=${item.thumbnailRotation}`"
                        :alt="item.title"
                        class="h-20 w-16 rounded-lg bg-gray-100 object-contain"
                    />
                    <div v-else class="h-20 w-16 rounded-lg bg-gray-200"></div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-gray-900">{{ item.player }}</p>
                        <p class="truncate text-sm text-gray-500">{{ item.title }}</p>
                        <p v-if="item.confidence !== null" class="text-xs text-gray-400">{{ Math.round(item.confidence) }}%</p>
                    </div>
                    <span class="text-sm font-semibold text-blue-600">View</span>
                </Link>
            </div>
        </template>
    </div>
</template>
