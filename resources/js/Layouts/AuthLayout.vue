<script setup lang="ts">
defineProps<{ title?: string }>();
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-semibold text-slate-900">MDS</h1>
                <p v-if="title" class="mt-1 text-slate-600 text-sm">{{ title }}</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-lg p-6 shadow-sm">
                <slot />
            </div>
        </div>
    </div>
</template>
