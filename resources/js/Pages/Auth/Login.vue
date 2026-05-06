<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

defineProps<{ canResetPassword?: boolean; status?: string }>();

const form = useForm({
    email: '',
    password: '',
    remember: false as boolean,
});

function submit() {
    form.transform((data) => ({ ...data, remember: data.remember ? 'on' : '' }))
        .post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Sign in" />
    <AuthLayout title="Sign in to your account">
        <div v-if="status" class="mb-4 text-sm text-emerald-700">{{ status }}</div>
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700">Email</label>
                <input v-model="form.email" type="email" required autofocus
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Password</label>
                <input v-model="form.password" type="password" required
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p>
            </div>
            <label class="flex items-center text-sm text-slate-700">
                <input v-model="form.remember" type="checkbox" class="mr-2" />
                Remember me
            </label>
            <button type="submit" :disabled="form.processing"
                class="w-full rounded bg-slate-900 text-white py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">
                Sign in
            </button>
            <div class="flex justify-between text-xs text-slate-600">
                <Link v-if="canResetPassword" href="/forgot-password" class="hover:underline">Forgot password?</Link>
                <Link href="/register" class="hover:underline">Create account</Link>
            </div>
        </form>
    </AuthLayout>
</template>
