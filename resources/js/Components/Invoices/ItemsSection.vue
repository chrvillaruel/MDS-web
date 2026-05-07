<script setup lang="ts">
import type { DraftLine, VatMode } from '@/Composables/useInvoiceDraft';

defineProps<{
    lines: DraftLine[];
    vatMode: VatMode;
    errors?: Record<string, string>;
}>();

defineEmits<{
    (e: 'add'): void;
    (e: 'remove', index: number): void;
    (e: 'update:vatMode', value: VatMode): void;
}>();
</script>

<template>
    <section class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm space-y-4">
        <header class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-900">2 · Items</h2>
            <div class="flex items-center gap-2 text-sm">
                <label class="text-slate-600">VAT mode</label>
                <select :value="vatMode" @change="$emit('update:vatMode', ($event.target as HTMLSelectElement).value as VatMode)"
                    class="rounded border-slate-300 px-2 py-1 border text-sm">
                    <option value="inclusive">Inclusive (price includes VAT)</option>
                    <option value="exclusive">Exclusive (add VAT on top)</option>
                </select>
            </div>
        </header>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-slate-500 uppercase">
                    <th class="py-1 w-2/5">Description</th>
                    <th class="py-1 w-16">Qty</th>
                    <th class="py-1 w-16">Unit</th>
                    <th class="py-1 w-24">Unit price</th>
                    <th class="py-1 w-32">VAT</th>
                    <th class="py-1 w-8"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(line, idx) in lines" :key="idx" class="border-t border-slate-100">
                    <td class="py-1">
                        <input v-model="line.description" required
                            class="w-full rounded border-slate-300 px-2 py-1 border" />
                    </td>
                    <td class="py-1">
                        <input v-model="line.quantity" type="number" min="0.0001" step="0.0001" required
                            class="w-full rounded border-slate-300 px-2 py-1 border text-right" />
                    </td>
                    <td class="py-1">
                        <input v-model="line.unit"
                            class="w-full rounded border-slate-300 px-2 py-1 border" />
                    </td>
                    <td class="py-1">
                        <input v-model="line.unit_price" type="number" min="0" step="0.01" required
                            class="w-full rounded border-slate-300 px-2 py-1 border text-right" />
                    </td>
                    <td class="py-1">
                        <select v-model="line.vat_classification"
                            class="w-full rounded border-slate-300 px-2 py-1 border">
                            <option value="vatable">Vatable</option>
                            <option value="vat_exempt">VAT-Exempt</option>
                            <option value="zero_rated">Zero-Rated</option>
                        </select>
                    </td>
                    <td class="py-1 text-center">
                        <button type="button" :disabled="lines.length === 1" class="text-rose-500 disabled:opacity-30"
                            aria-label="Remove line"
                            @click="$emit('remove', idx)">
                            ×
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <button type="button" class="text-sm text-slate-700 hover:underline" @click="$emit('add')">
            + Add line
        </button>
    </section>
</template>
