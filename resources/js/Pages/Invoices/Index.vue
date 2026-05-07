<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    invoices: Array<{
        id: number;
        document_type: string;
        status: string;
        serial: string | null;
        total_amount: string;
        issued_at: string | null;
        created_at: string | null;
    }>;
}>();
</script>

<template>
    <Head title="Invoices" />
    <div class="min-h-screen bg-slate-50 px-4 py-8">
        <div class="max-w-4xl mx-auto space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-slate-900">Invoices</h1>
                <a href="/invoices/create"
                    class="rounded bg-slate-900 text-white px-3 py-2 text-sm hover:bg-slate-800">
                    + New invoice
                </a>
            </div>

            <div v-if="invoices.length === 0" class="bg-white rounded-lg border border-slate-200 p-8 text-center text-sm text-slate-600">
                No invoices yet. <a href="/invoices/create" class="underline">Create your first one.</a>
            </div>

            <div v-else class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 uppercase border-b">
                            <th class="px-4 py-2">Serial</th>
                            <th class="px-4 py-2">Type</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right">Total</th>
                            <th class="px-4 py-2">Issued</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="i in invoices" :key="i.id" class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-2 font-mono">
                                <a :href="`/invoices/${i.id}`" class="hover:underline">
                                    {{ i.serial ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-2">{{ i.document_type }}</td>
                            <td class="px-4 py-2">
                                <span :class="{
                                    'text-emerald-700': i.status === 'issued',
                                    'text-rose-700': i.status === 'voided',
                                    'text-slate-700': i.status === 'draft',
                                }">{{ i.status }}</span>
                            </td>
                            <td class="px-4 py-2 text-right">₱{{ i.total_amount }}</td>
                            <td class="px-4 py-2">{{ i.issued_at ?? i.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
