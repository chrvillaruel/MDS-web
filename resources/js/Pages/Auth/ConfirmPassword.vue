<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const form = useForm({ password: '' });
function submit() { form.post('/user/confirm-password'); }
</script>

<template>
    <Head title="Confirm password" />
    <AuthLayout title="Confirm your password">
        <p class="text-sm text-slate-600 mb-4">
            Please confirm your password to continue with this sensitive action.
        </p>
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700">Password</label>
                <input v-model="form.password" type="password" required autofocus
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p>
            </div>
            <button type="submit" :disabled="form.processing"
                class="w-full rounded bg-slate-900 text-white py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">
                Confirm
            </button>
        </form>
    </AuthLayout>
</template>
