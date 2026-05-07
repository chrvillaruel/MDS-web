<?php

declare(strict_types=1);

use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Branch;
use Modules\Identity\Domain\Seller;
use Modules\Invoicing\Actions\IssueInvoiceAction;
use Modules\Invoicing\Domain\Buyer;
use Modules\Invoicing\Domain\EisPayloadBuilderV1;
use Modules\Invoicing\Domain\Invoice;
use Modules\Invoicing\Domain\InvoiceLine;
use Modules\Invoicing\Domain\SerialAllocator;
use Modules\Invoicing\Domain\VatCalculator;
use Modules\Invoicing\Infrastructure\HtmlInvoicePdfRenderer;
use Modules\Invoicing\Jobs\RenderInvoicePdf;

uses(RefreshDatabase::class);

afterEach(fn () => TenantContext::clear());

function issueInvoiceForRender(): Invoice
{
    $seller = Seller::factory()->create();
    TenantContext::set($seller->id);
    $branch = Branch::factory()->for($seller)->create();
    $buyer = Buyer::factory()->for($seller)->create();

    /** @var Invoice $invoice */
    $invoice = Invoice::create([
        'branch_id' => $branch->id,
        'buyer_id' => $buyer->id,
        'document_type' => 'sales_invoice',
        'vat_mode' => 'inclusive',
        'status' => 'draft',
        'currency' => 'PHP',
    ]);
    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'line_number' => 1,
        'description' => 'Test ñame with áccent',
        'quantity' => '1.0000',
        'unit' => 'pc',
        'unit_price' => '1120.00',
        'line_total' => '1120.0000',
        'vat_classification' => 'vatable',
    ]);
    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'line_number' => 2,
        'description' => 'Export item',
        'quantity' => '1.0000',
        'unit' => 'pc',
        'unit_price' => '500.00',
        'line_total' => '500.0000',
        'vat_classification' => 'zero_rated',
    ]);

    $action = new IssueInvoiceAction(new SerialAllocator, new VatCalculator, new EisPayloadBuilderV1);

    return $action->execute($invoice->id, (string) Str::uuid());
}

it('renders an HTML artifact containing the 13 BIR fields', function () {
    $invoice = issueInvoiceForRender();
    $renderer = new HtmlInvoicePdfRenderer(app('view'));

    $html = $renderer->render($invoice);

    expect($html)
        ->toContain($invoice->canonical_payload['serial']['formatted'])
        ->toContain($invoice->eis_unique_id)
        ->toContain($invoice->canonical_payload['seller']['registered_name'])
        ->toContain($invoice->canonical_payload['seller']['tin'])
        ->toContain($invoice->canonical_payload['seller']['address'])
        ->toContain('VATable Sales')
        ->toContain('VAT-Exempt Sales')
        ->toContain('Zero-Rated Sales')
        ->toContain('VAT Amount')
        ->toContain('TOTAL AMOUNT DUE')
        // Filipino diacritics survive the template (per acceptance criteria).
        ->toContain('ñame')
        ->toContain('áccent')
        // Zero-rated stamp on zero-rated sales.
        ->toContain('ZERO-RATED SALE');
});

it('persists the artifact path on the invoice and writes the file', function () {
    Storage::fake('local');
    $invoice = issueInvoiceForRender();

    (new RenderInvoicePdf($invoice->id, $invoice->seller_id))
        ->handle(new HtmlInvoicePdfRenderer(app('view')));

    $invoice->refresh();
    expect($invoice->pdf_path)->not->toBeNull()
        ->and($invoice->pdf_rendered_at)->not->toBeNull();

    Storage::disk('local')->assertExists($invoice->pdf_path);
});

it('renders the VOIDED watermark when the invoice is voided', function () {
    $invoice = issueInvoiceForRender();
    $invoice->forceFill([
        'status' => 'voided',
        'voided_at' => now(),
        'void_reason' => 'Wrong amount entered by cashier — re-issued.',
    ])->save();

    $html = (new HtmlInvoicePdfRenderer(app('view')))->render($invoice);

    expect($html)->toContain('void-watermark')
        ->toContain('VOIDED')
        ->toContain('Wrong amount entered');
});
