<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

defineProps<{ status?: string }>();

const resend = useForm({});
const logout = useForm({});
</script>

<template>
    <Head title="Verify email" />
    <AuthLayout title="Verify your email">
        <p class="text-sm text-slate-600 mb-4">
            We sent a verification link to your email. Please click it before issuing
            invoices (BIR requires verified contact details on every invoice).
        </p>
        <div v-if="status === 'verification-link-sent'" class="mb-4 text-sm text-emerald-700">
            A new verification link has been sent.
        </div>
        <div class="space-y-3">
            <button @click="resend.post('/email/verification-notification')"
                :disabled="resend.processing"
                class="w-full rounded bg-slate-900 text-white py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">
                Resend verification email
            </button>
            <button @click="logout.post('/logout')" :disabled="logout.processing"
                class="w-full rounded border border-slate-300 py-2 text-sm font-medium hover:bg-slate-50 disabled:opacity-50">
                Log out
            </button>
        </div>
    </AuthLayout>
</template>
