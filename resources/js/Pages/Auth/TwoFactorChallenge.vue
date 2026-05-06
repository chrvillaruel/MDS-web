<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const useRecovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });

function submit() { form.post('/two-factor-challenge'); }
</script>

<template>
    <Head title="Two-factor authentication" />
    <AuthLayout title="Two-factor authentication">
        <p class="text-sm text-slate-600 mb-4">
            <span v-if="!useRecovery">Enter the 6-digit code from your authenticator app.</span>
            <span v-else>Enter one of your recovery codes.</span>
        </p>
        <form class="space-y-4" @submit.prevent="submit">
            <div v-if="!useRecovery">
                <label class="block text-sm font-medium text-slate-700">Code</label>
                <input v-model="form.code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                <p v-if="form.errors.code" class="mt-1 text-xs text-rose-600">{{ form.errors.code }}</p>
            </div>
            <div v-else>
                <label class="block text-sm font-medium text-slate-700">Recovery code</label>
                <input v-model="form.recovery_code" type="text" required
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                <p v-if="form.errors.recovery_code" class="mt-1 text-xs text-rose-600">{{ form.errors.recovery_code }}</p>
            </div>
            <button type="submit" :disabled="form.processing"
                class="w-full rounded bg-slate-900 text-white py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">
                Verify
            </button>
            <button type="button" @click="useRecovery = !useRecovery"
                class="w-full text-xs text-slate-600 hover:underline">
                {{ useRecovery ? 'Use authenticator code' : 'Use a recovery code' }}
            </button>
        </form>
    </AuthLayout>
</template>
