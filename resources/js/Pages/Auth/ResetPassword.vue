<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps<{ email: string; token: string }>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() { form.post('/reset-password'); }
</script>

<template>
    <Head title="Reset password" />
    <AuthLayout title="Reset your password">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700">Email</label>
                <input v-model="form.email" type="email" required
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">New password</label>
                <input v-model="form.password" type="password" required minlength="12"
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Confirm new password</label>
                <input v-model="form.password_confirmation" type="password" required
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
            </div>
            <button type="submit" :disabled="form.processing"
                class="w-full rounded bg-slate-900 text-white py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">
                Reset password
            </button>
        </form>
    </AuthLayout>
</template>
