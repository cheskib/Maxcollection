<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
});

function submit(): void {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Login" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-6">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900">Max Collection</h1>
            <p class="mt-1 text-sm text-gray-500">Administrator Login</p>
        </div>

        <form class="mt-8 space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                />
            </div>

            <p v-if="form.errors.email" class="text-sm text-red-600">{{ form.errors.email }}</p>
            <p v-if="form.errors.password" class="text-sm text-red-600">{{ form.errors.password }}</p>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-blue-600 py-2.5 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
            >
                Login
            </button>
        </form>
    </div>
</template>
