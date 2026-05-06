<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

defineProps<{ status?: string }>();

const form = useForm({ email: '' });
function submit() { form.post('/forgot-password'); }
</script>

<template>
    <Head title="Forgot password" />
    <AuthLayout title="Forgot your password?">
        <div v-if="status" class="mb-4 text-sm text-emerald-700">{{ status }}</div>
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700">Email</label>
                <input v-model="form.email" type="email" required autofocus
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
            </div>
            <button type="submit" :disabled="form.processing"
                class="w-full rounded bg-slate-900 text-white py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">
                Email password reset link
            </button>
            <div class="text-center text-xs text-slate-600">
                <Link href="/login" class="hover:underline">Back to sign in</Link>
            </div>
        </form>
    </AuthLayout>
</template>
