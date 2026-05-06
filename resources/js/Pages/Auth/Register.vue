<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Create account" />
    <AuthLayout title="Create your MDS account">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700">Name</label>
                <input v-model="form.name" type="text" required autofocus
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Email</label>
                <input v-model="form.email" type="email" required
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Password</label>
                <input v-model="form.password" type="password" required minlength="12"
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Confirm password</label>
                <input v-model="form.password_confirmation" type="password" required
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
            </div>
            <button type="submit" :disabled="form.processing"
                class="w-full rounded bg-slate-900 text-white py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">
                Create account
            </button>
            <div class="text-center text-xs text-slate-600">
                Already have an account?
                <Link href="/login" class="hover:underline">Sign in</Link>
            </div>
        </form>
    </AuthLayout>
</template>
