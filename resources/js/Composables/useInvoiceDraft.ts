import { computed, reactive } from 'vue';

export type VatClassification = 'vatable' | 'vat_exempt' | 'zero_rated';
export type VatMode = 'inclusive' | 'exclusive';

export interface DraftLine {
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    vat_classification: VatClassification;
}

export interface DraftBuyer {
    id: number | null;
    registered_name: string;
    tin: string;
    address: string;
    email: string;
}

export interface InvoiceDraft {
    branch_id: number | null;
    document_type: 'sales_invoice' | 'credit_note' | 'debit_note' | 'official_receipt';
    vat_mode: VatMode;
    supersedes_invoice_id: number | null;
    idempotency_key: string;
    buyer: DraftBuyer;
    lines: DraftLine[];
}

const VAT_RATE = 0.12;

function newLine(): DraftLine {
    return {
        description: '',
        quantity: '1',
        unit: 'pc',
        unit_price: '0',
        vat_classification: 'vatable',
    };
}

function newBuyer(): DraftBuyer {
    return { id: null, registered_name: '', tin: '', address: '', email: '' };
}

function uuidv4(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

export function useInvoiceDraft(initial: Partial<InvoiceDraft> = {}) {
    const draft = reactive<InvoiceDraft>({
        branch_id: initial.branch_id ?? null,
        document_type: initial.document_type ?? 'sales_invoice',
        vat_mode: initial.vat_mode ?? 'inclusive',
        supersedes_invoice_id: initial.supersedes_invoice_id ?? null,
        idempotency_key: uuidv4(),
        buyer: { ...newBuyer(), ...(initial.buyer ?? {}) },
        lines: initial.lines && initial.lines.length > 0 ? initial.lines.map((l) => ({ ...l })) : [newLine()],
    });

    function addLine() {
        draft.lines.push(newLine());
    }

    function removeLine(index: number) {
        if (draft.lines.length === 1) return;
        draft.lines.splice(index, 1);
    }

    function regenerateKey() {
        draft.idempotency_key = uuidv4();
    }

    const totals = computed(() => {
        // Mirror VatCalculator. Numbers in pesos for display; server is source of truth.
        let vatable = 0;
        let vat = 0;
        let exempt = 0;
        let zero = 0;

        for (const line of draft.lines) {
            const qty = parseFloat(line.quantity) || 0;
            const price = parseFloat(line.unit_price) || 0;
            if (qty <= 0 || price < 0) continue;
            const gross = Math.round(qty * price * 100) / 100;

            if (line.vat_classification === 'zero_rated') {
                zero += gross;
            } else if (line.vat_classification === 'vat_exempt') {
                exempt += gross;
            } else if (draft.vat_mode === 'inclusive') {
                const lineVat = Math.round(((gross * VAT_RATE) / (1 + VAT_RATE)) * 100) / 100;
                vat += lineVat;
                vatable += gross - lineVat;
            } else {
                const lineVat = Math.round(gross * VAT_RATE * 100) / 100;
                vat += lineVat;
                vatable += gross;
            }
        }
        const subtotal = vatable + exempt + zero;
        const total = subtotal + vat;
        return { vatable, vat, exempt, zero, subtotal, total };
    });

    const canIssue = computed(
        () =>
            draft.branch_id !== null &&
            draft.lines.length > 0 &&
            draft.lines.every(
                (l) =>
                    l.description.trim().length > 0 &&
                    parseFloat(l.quantity) > 0 &&
                    parseFloat(l.unit_price) >= 0,
            ) &&
            (draft.buyer.id !== null || draft.buyer.registered_name.trim().length > 0),
    );

    return { draft, totals, canIssue, addLine, removeLine, regenerateKey };
}
