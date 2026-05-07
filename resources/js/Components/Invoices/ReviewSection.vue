<script setup lang="ts">
defineProps<{
    totals: { vatable: number; vat: number; exempt: number; zero: number; subtotal: number; total: number };
    canIssue: boolean;
    processing: boolean;
}>();

defineEmits<{ (e: 'issue'): void }>();

function peso(n: number) {
    return n.toLocaleString('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
</script>

<template>
    <section class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm space-y-4">
        <header>
            <h2 class="text-base font-semibold text-slate-900">3 · Review &amp; Issue</h2>
            <p class="text-sm text-slate-600 mt-1">
                Once issued, the invoice is committed: it gets a serial number, a frozen canonical payload, and an
                append-only audit trail. To correct an issued invoice, void it and issue a credit note.
            </p>
        </header>

        <dl class="grid grid-cols-2 gap-y-1 text-sm max-w-md">
            <dt class="text-slate-600">VATable Sales</dt>
            <dd class="text-right">{{ peso(totals.vatable) }}</dd>
            <dt class="text-slate-600">VAT-Exempt Sales</dt>
            <dd class="text-right">{{ peso(totals.exempt) }}</dd>
            <dt class="text-slate-600">Zero-Rated Sales</dt>
            <dd class="text-right">{{ peso(totals.zero) }}</dd>
            <dt class="text-slate-600">VAT Amount (12%)</dt>
            <dd class="text-right">{{ peso(totals.vat) }}</dd>
            <dt class="text-slate-600 border-t pt-1">Subtotal</dt>
            <dd class="text-right border-t pt-1">{{ peso(totals.subtotal) }}</dd>
            <dt class="border-t pt-1 font-semibold">Total Amount Due</dt>
            <dd class="text-right border-t pt-1 font-semibold">{{ peso(totals.total) }}</dd>
        </dl>

        <button type="button" :disabled="!canIssue || processing" data-testid="issue-button"
            class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-medium px-4 py-3"
            @click="$emit('issue')">
            <span v-if="processing">Issuing…</span>
            <span v-else>Issue Invoice</span>
        </button>
        <p class="text-xs text-slate-500 text-center">
            Estimated under 1.2 s · audit-trail recorded · idempotent on retry
        </p>
    </section>
</template>
