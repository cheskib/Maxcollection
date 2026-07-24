<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    laravelVersion: string;
    phpVersion: string;
}>();
</script>

<template>
    <Head title="Welcome" />
    <div class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center px-6 text-center">
        <h1 class="text-3xl font-bold text-gray-900">Max Collection</h1>
        <p class="mt-2 text-gray-600">AI-assisted collectible management</p>
        <p class="mt-6 text-sm text-gray-400">
            Laravel {{ laravelVersion }} · PHP {{ phpVersion }} · Vue 3 · Inertia · Tailwind
        </p>
    </div>
</template>
