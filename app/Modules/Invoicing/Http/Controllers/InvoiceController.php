<?php

declare(strict_types=1);

namespace Modules\Invoicing\Http\Controllers;

use App\Support\TenantContext;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Domain\Branch;
use Modules\Invoicing\Actions\IssueInvoiceAction;
use Modules\Invoicing\Actions\VoidInvoiceAction;
use Modules\Invoicing\Domain\Buyer;
use Modules\Invoicing\Domain\IdempotencyConflictException;
use Modules\Invoicing\Domain\Invoice;
use Modules\Invoicing\Domain\InvoiceLine;
use Modules\Invoicing\Domain\InvoiceNotIssuableException;
use Modules\Invoicing\Jobs\RenderInvoicePdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class InvoiceController
{
    public function __construct(
        private IssueInvoiceAction $issuer,
        private VoidInvoiceAction $voider,
    ) {}

    /**
     * Larastan reads the migration column as string|null and overrides our cast,
     * so we coerce defensively at the boundary.
     */
    private function iso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return (string) $value;
    }

    public function index(Request $request): Response
    {
        $invoices = Invoice::orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'document_type', 'status', 'serial_number', 'reset_counter', 'total_amount', 'issued_at', 'created_at']);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices->map(fn (Invoice $i): array => [
                'id' => $i->id,
                'document_type' => (string) $i->document_type,
                'status' => (string) $i->status,
                'serial' => $i->serial_number
                    ? sprintf('%010d-%02d', (int) $i->serial_number, (int) $i->reset_counter)
                    : null,
                'total_amount' => (string) $i->total_amount,
                'issued_at' => $this->iso($i->issued_at),
                'created_at' => $this->iso($i->created_at),
            ])->values()->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $branches = Branch::orderBy('code')->get(['id', 'code', 'name']);
        $recentBuyers = Buyer::orderByDesc('updated_at')->limit(20)
            ->get(['id', 'registered_name', 'tin', 'email']);

        return Inertia::render('Invoices/Create', [
            'branches' => $branches,
            'recent_buyers' => $recentBuyers,
            'default_branch_id' => $branches->first()?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sellerId = TenantContext::sellerId();
        abort_unless($sellerId !== null, 403);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'document_type' => ['required', 'in:sales_invoice,credit_note,debit_note,official_receipt'],
            'vat_mode' => ['required', 'in:inclusive,exclusive'],
            'supersedes_invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'idempotency_key' => ['required', 'string', 'size:36'],

            'buyer.id' => ['nullable', 'integer', 'exists:buyers,id'],
            'buyer.registered_name' => ['required_without:buyer.id', 'nullable', 'string', 'max:255'],
            'buyer.email' => ['nullable', 'email'],
            'buyer.tin' => ['nullable', 'string', 'size:9'],
            'buyer.address' => ['nullable', 'string', 'max:1000'],

            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['required', 'string', 'max:16'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'lines.*.vat_classification' => ['required', 'in:vatable,vat_exempt,zero_rated'],
        ]);

        try {
            $invoiceId = DB::transaction(function () use ($data) {
                $buyerId = $data['buyer']['id'] ?? null;
                if ($buyerId === null && ! empty($data['buyer']['registered_name'])) {
                    $buyer = Buyer::create([
                        'registered_name' => $data['buyer']['registered_name'],
                        'tin' => $data['buyer']['tin'] ?? null,
                        'address' => $data['buyer']['address'] ?? null,
                        'email' => $data['buyer']['email'] ?? null,
                    ]);
                    $buyerId = $buyer->id;
                }

                /** @var Invoice $invoice */
                $invoice = Invoice::create([
                    'branch_id' => $data['branch_id'],
                    'buyer_id' => $buyerId,
                    'supersedes_invoice_id' => $data['supersedes_invoice_id'] ?? null,
                    'document_type' => $data['document_type'],
                    'vat_mode' => $data['vat_mode'],
                    'status' => 'draft',
                    'currency' => 'PHP',
                    'source' => 'manual',
                ]);

                foreach ($data['lines'] as $i => $line) {
                    InvoiceLine::create([
                        'invoice_id' => $invoice->id,
                        'line_number' => $i + 1,
                        'description' => $line['description'],
                        'quantity' => number_format((float) $line['quantity'], 4, '.', ''),
                        'unit' => $line['unit'],
                        'unit_price' => number_format((float) $line['unit_price'], 4, '.', ''),
                        'line_total' => number_format(
                            (float) $line['quantity'] * (float) $line['unit_price'],
                            4, '.', '',
                        ),
                        'vat_classification' => $line['vat_classification'],
                    ]);
                }

                return $invoice->id;
            });

            $issued = $this->issuer->execute(
                $invoiceId,
                $data['idempotency_key'],
                is_int(Auth::id()) ? Auth::id() : null,
                $request->ip(),
            );
        } catch (IdempotencyConflictException $e) {
            return back()->withErrors(['idempotency_key' => $e->getMessage()])->withInput();
        } catch (InvoiceNotIssuableException $e) {
            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        }

        // Render the PDF synchronously so the seller can download it on the
        // success screen — manual flow is interactive (PRD AC-6.6.1, < 60s).
        Bus::dispatchSync(new RenderInvoicePdf($issued->id, $issued->seller_id));

        return redirect()->route('invoices.show', $issued->id)
            ->with('success', "Issued {$issued->document_type} {$issued->serial_number}.");
    }

    public function show(Request $request, int $id): Response
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::with(['lines', 'events'])->findOrFail($id);

        return Inertia::render('Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'status' => $invoice->status,
                'document_type' => $invoice->document_type,
                'vat_mode' => $invoice->vat_mode,
                'serial' => $invoice->serial_number
                    ? sprintf('%010d-%02d', $invoice->serial_number, $invoice->reset_counter)
                    : null,
                'eis_unique_id' => $invoice->eis_unique_id,
                'total_amount' => $invoice->total_amount,
                'subtotal' => $invoice->subtotal,
                'vat_amount' => $invoice->vat_amount,
                'issued_at' => $this->iso($invoice->issued_at),
                'voided_at' => $this->iso($invoice->voided_at),
                'void_reason' => $invoice->void_reason,
                'pdf_url' => $invoice->pdf_path ? route('invoices.pdf', $invoice->id) : null,
                'lines' => $invoice->lines->map(fn ($l) => [
                    'line_number' => $l->line_number,
                    'description' => $l->description,
                    'quantity' => $l->quantity,
                    'unit' => $l->unit,
                    'unit_price' => $l->unit_price,
                    'line_total' => $l->line_total,
                    'vat_classification' => $l->vat_classification,
                ]),
                'canonical_payload' => $invoice->canonical_payload,
            ],
        ]);
    }

    public function pdf(Request $request, int $id): BinaryFileResponse
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::findOrFail($id);

        abort_unless($invoice->pdf_path !== null, 404);

        return response()->file(
            storage_path('app/private/'.$invoice->pdf_path),
            ['Content-Disposition' => 'inline; filename="invoice-'.$invoice->id.'.html"'],
        );
    }

    public function void(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        try {
            $this->voider->execute(
                $id,
                $data['reason'],
                is_int(Auth::id()) ? Auth::id() : null,
                $request->ip(),
            );
        } catch (InvoiceNotIssuableException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('success', 'Invoice voided.');
    }
}
