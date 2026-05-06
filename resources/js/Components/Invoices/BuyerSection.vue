<script setup lang="ts">
import { computed, ref } from 'vue';
import type { DraftBuyer } from '@/Composables/useInvoiceDraft';

const props = defineProps<{
    buyer: DraftBuyer;
    recentBuyers: Array<{ id: number; registered_name: string; tin: string | null; email: string | null }>;
    errors?: Record<string, string>;
}>();

const query = ref('');

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.recentBuyers.slice(0, 5);
    return props.recentBuyers
        .filter(
            (b) =>
                b.registered_name?.toLowerCase().includes(q) ||
                b.tin?.includes(q) ||
                b.email?.toLowerCase().includes(q),
        )
        .slice(0, 5);
});

function pick(b: { id: number; registered_name: string; tin: string | null; email: string | null }) {
    props.buyer.id = b.id;
    props.buyer.registered_name = b.registered_name;
    props.buyer.tin = b.tin ?? '';
    props.buyer.email = b.email ?? '';
    query.value = b.registered_name;
}

function clear() {
    props.buyer.id = null;
    props.buyer.registered_name = '';
    props.buyer.tin = '';
    props.buyer.address = '';
    props.buyer.email = '';
    query.value = '';
}
</script>

<template>
    <section class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm space-y-4">
        <header class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-900">1 · Buyer</h2>
            <button v-if="buyer.id || buyer.registered_name" type="button" class="text-xs text-slate-500 hover:underline"
                @click="clear">
                Clear
            </button>
        </header>

        <div>
            <label class="block text-sm font-medium text-slate-700">Search prior buyers or enter a new one</label>
            <input v-model="query" autocomplete="off" placeholder="Name, TIN, or email…"
                class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />

            <ul v-if="filtered.length > 0 && !buyer.id" class="mt-1 border border-slate-200 rounded text-sm max-h-40 overflow-y-auto">
                <li v-for="b in filtered" :key="b.id" class="px-3 py-2 hover:bg-slate-50 cursor-pointer"
                    @click="pick(b)">
                    <div class="font-medium">{{ b.registered_name }}</div>
                    <div class="text-xs text-slate-500">{{ b.tin || 'no TIN' }} · {{ b.email || 'no email' }}</div>
                </li>
            </ul>
        </div>

        <div v-if="!buyer.id" class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm font-medium text-slate-700">Name</label>
                <input v-model="buyer.registered_name" required
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                <p v-if="errors?.['buyer.registered_name']" class="text-xs text-rose-600 mt-1">
                    {{ errors['buyer.registered_name'] }}
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Email (optional)</label>
                <input v-model="buyer.email" type="email"
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">TIN (optional)</label>
                <input v-model="buyer.tin" maxlength="9"
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-medium text-slate-700">Address (optional)</label>
                <textarea v-model="buyer.address" rows="2"
                    class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
            </div>
        </div>

        <p v-else class="text-sm text-slate-700">
            Using saved buyer:
            <strong>{{ buyer.registered_name }}</strong>
            <span v-if="buyer.tin" class="text-slate-500"> · TIN {{ buyer.tin }}</span>
        </p>
    </section>
</template>
