<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    laravelVersion: string;
    phpVersion: string;
}>();
</script>

<template>
    <Head title="Welcome" />
    <div class="min-h-screen bg-slate-50 flex items-center justify-center p-6">
        <div class="max-w-2xl text-center">
            <h1 class="text-3xl font-semibold text-slate-900">Million Dollar Software</h1>
            <p class="mt-2 text-slate-600">
                BIR-accreditable electronic invoicing for Filipino online sellers.
            </p>
            <p class="mt-6 text-sm text-slate-500">
                Laravel {{ laravelVersion }} · PHP {{ phpVersion }}
            </p>
        </div>
    </div>
</template>
