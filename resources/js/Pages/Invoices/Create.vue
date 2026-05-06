<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import BuyerSection from '@/Components/Invoices/BuyerSection.vue';
import ItemsSection from '@/Components/Invoices/ItemsSection.vue';
import ReviewSection from '@/Components/Invoices/ReviewSection.vue';
import { useInvoiceDraft } from '@/Composables/useInvoiceDraft';

const props = defineProps<{
    branches: Array<{ id: number; code: string; name: string }>;
    recent_buyers: Array<{ id: number; registered_name: string; tin: string | null; email: string | null }>;
    default_branch_id: number | null;
}>();

const { draft, totals, canIssue, addLine, removeLine, regenerateKey } = useInvoiceDraft({
    branch_id: props.default_branch_id,
});

const form = useForm({
    branch_id: draft.branch_id,
    document_type: draft.document_type,
    vat_mode: draft.vat_mode,
    supersedes_invoice_id: draft.supersedes_invoice_id,
    idempotency_key: draft.idempotency_key,
    buyer: draft.buyer,
    lines: draft.lines,
});

function issue() {
    form.transform(() => ({
        branch_id: draft.branch_id,
        document_type: draft.document_type,
        vat_mode: draft.vat_mode,
        supersedes_invoice_id: draft.supersedes_invoice_id,
        idempotency_key: draft.idempotency_key,
        buyer: draft.buyer,
        lines: draft.lines,
    })).post('/invoices', {
        onError: () => {
            // Refresh idempotency key if the server rejected the body — a fresh
            // submission with the same key would 409 on the server's idempotency
            // check (PRD TR-6.5.3).
            regenerateKey();
        },
    });
}
</script>

<template>
    <Head title="New Invoice" />
    <div class="min-h-screen bg-slate-50 px-4 py-8">
        <div class="max-w-4xl mx-auto space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-slate-900">New Invoice</h1>
                <div class="flex items-center gap-3 text-sm">
                    <label class="text-slate-600">Branch</label>
                    <select v-model="draft.branch_id"
                        class="rounded border-slate-300 px-2 py-1 border text-sm">
                        <option v-for="b in branches" :key="b.id" :value="b.id">
                            {{ b.code }} — {{ b.name }}
                        </option>
                    </select>
                    <label class="text-slate-600 ml-2">Type</label>
                    <select v-model="draft.document_type"
                        class="rounded border-slate-300 px-2 py-1 border text-sm">
                        <option value="sales_invoice">Sales Invoice</option>
                        <option value="credit_note">Credit Note</option>
                        <option value="debit_note">Debit Note</option>
                    </select>
                </div>
            </div>

            <div v-if="(form.errors as Record<string,string>).form" class="rounded bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800">
                {{ (form.errors as Record<string,string>).form }}
            </div>

            <BuyerSection :buyer="draft.buyer" :recent-buyers="recent_buyers" :errors="form.errors as any" />

            <ItemsSection
                :lines="draft.lines"
                :vat-mode="draft.vat_mode"
                :errors="form.errors as any"
                @add="addLine"
                @remove="(i: number) => removeLine(i)"
                @update:vat-mode="(v) => (draft.vat_mode = v)"
            />

            <ReviewSection :totals="totals" :can-issue="canIssue" :processing="form.processing" @issue="issue" />
        </div>
    </div>
</template>
