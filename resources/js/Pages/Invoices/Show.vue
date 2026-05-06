<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    invoice: {
        id: number;
        status: string;
        document_type: string;
        vat_mode: string;
        serial: string | null;
        eis_unique_id: string | null;
        total_amount: string;
        subtotal: string;
        vat_amount: string;
        issued_at: string | null;
        voided_at: string | null;
        void_reason: string | null;
        pdf_url: string | null;
        lines: Array<{
            line_number: number;
            description: string;
            quantity: string;
            unit: string;
            unit_price: string;
            line_total: string;
            vat_classification: string;
        }>;
        canonical_payload: Record<string, unknown> | null;
    };
}>();

const showVoid = ref(false);
const voidForm = useForm({ reason: '' });

function submitVoid() {
    voidForm.post(`/invoices/${props.invoice.id}/void`, {
        onSuccess: () => (showVoid.value = false),
    });
}
</script>

<template>
    <Head :title="`Invoice ${invoice.serial ?? invoice.id}`" />
    <div class="min-h-screen bg-slate-50 px-4 py-8">
        <div class="max-w-3xl mx-auto space-y-4">
            <header class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">
                        {{ invoice.document_type === 'credit_note' ? 'Credit Note' : invoice.document_type === 'debit_note' ? 'Debit Note' : 'Sales Invoice' }}
                        <span v-if="invoice.serial" class="text-slate-500 font-mono">{{ invoice.serial }}</span>
                    </h1>
                    <p class="text-sm text-slate-600">Status:
                        <span :class="{
                            'text-emerald-700': invoice.status === 'issued',
                            'text-rose-700': invoice.status === 'voided',
                            'text-slate-700': invoice.status === 'draft',
                        }">{{ invoice.status }}</span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <a v-if="invoice.pdf_url" :href="invoice.pdf_url" target="_blank"
                        class="rounded border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">
                        View document
                    </a>
                    <a href="/invoices/create"
                        class="rounded bg-slate-900 text-white px-3 py-2 text-sm hover:bg-slate-800">
                        Create another
                    </a>
                </div>
            </header>

            <section class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm space-y-3">
                <dl class="grid grid-cols-2 gap-y-1 text-sm">
                    <dt class="text-slate-500">EIS Unique ID</dt>
                    <dd class="font-mono">{{ invoice.eis_unique_id }}</dd>
                    <dt class="text-slate-500">Issued at</dt>
                    <dd>{{ invoice.issued_at }}</dd>
                    <template v-if="invoice.voided_at">
                        <dt class="text-slate-500">Voided at</dt>
                        <dd>{{ invoice.voided_at }}</dd>
                        <dt class="text-slate-500">Void reason</dt>
                        <dd>{{ invoice.void_reason }}</dd>
                    </template>
                    <dt class="text-slate-500">Subtotal</dt>
                    <dd>₱{{ invoice.subtotal }}</dd>
                    <dt class="text-slate-500">VAT</dt>
                    <dd>₱{{ invoice.vat_amount }}</dd>
                    <dt class="text-slate-500 font-medium">Total</dt>
                    <dd class="font-medium">₱{{ invoice.total_amount }}</dd>
                </dl>
            </section>

            <section class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900 mb-2">Lines</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 uppercase">
                            <th>#</th>
                            <th>Description</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th>VAT</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in invoice.lines" :key="line.line_number" class="border-t border-slate-100">
                            <td>{{ line.line_number }}</td>
                            <td>{{ line.description }}</td>
                            <td class="text-right">{{ line.quantity }}</td>
                            <td class="text-right">{{ line.unit_price }}</td>
                            <td>{{ line.vat_classification }}</td>
                            <td class="text-right">{{ line.line_total }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section v-if="invoice.status === 'issued'" class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900 mb-2">Void this invoice</h2>
                <p class="text-sm text-slate-600 mb-3">
                    Voids are permanent. The serial number is retained and never reused; the audit trail records the
                    reason. Use a credit note to issue a corrective document.
                </p>
                <button v-if="!showVoid" type="button"
                    class="rounded border border-rose-300 bg-white px-3 py-2 text-sm text-rose-700 hover:bg-rose-50"
                    @click="showVoid = true">
                    Void invoice…
                </button>
                <form v-else class="space-y-2" @submit.prevent="submitVoid">
                    <textarea v-model="voidForm.reason" required minlength="10" rows="3"
                        placeholder="Reason for voiding (≥ 10 characters)"
                        class="w-full rounded border-slate-300 px-3 py-2 border text-sm" />
                    <p v-if="voidForm.errors.reason" class="text-xs text-rose-600">{{ voidForm.errors.reason }}</p>
                    <div class="flex gap-2">
                        <button type="submit" :disabled="voidForm.processing"
                            class="rounded bg-rose-600 text-white px-3 py-2 text-sm hover:bg-rose-700 disabled:opacity-50">
                            Confirm void
                        </button>
                        <button type="button" class="text-sm text-slate-600 hover:underline" @click="showVoid = false">
                            Cancel
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>
