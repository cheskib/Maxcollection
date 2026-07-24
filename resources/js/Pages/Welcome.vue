<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';

const page = usePage<{ auth: { user: { name: string; email: string } | null } }>();

function logout(): void {
    router.post('/logout');
}
</script>

<template>
    <Head title="Home" />
    <div class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center px-6 text-center">
        <h1 class="text-3xl font-bold text-gray-900">Max Collection</h1>
        <p class="mt-2 text-gray-600">Logged in as {{ page.props.auth.user?.email }}</p>
        <p class="mt-1 text-sm text-gray-400">The Home screen arrives in Milestone 3.</p>
        <button
            class="mt-8 w-full rounded-lg bg-gray-200 py-2.5 font-semibold text-gray-700 hover:bg-gray-300"
            @click="logout"
        >
            Logout
        </button>
    </div>
</template>
