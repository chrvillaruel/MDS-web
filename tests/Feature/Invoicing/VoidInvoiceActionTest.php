<?php

declare(strict_types=1);

use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Branch;
use Modules\Identity\Domain\Seller;
use Modules\Invoicing\Actions\IssueInvoiceAction;
use Modules\Invoicing\Actions\VoidInvoiceAction;
use Modules\Invoicing\Domain\Buyer;
use Modules\Invoicing\Domain\EisPayloadBuilderV1;
use Modules\Invoicing\Domain\Invoice;
use Modules\Invoicing\Domain\InvoiceEvent;
use Modules\Invoicing\Domain\InvoiceLine;
use Modules\Invoicing\Domain\InvoiceNotIssuableException;
use Modules\Invoicing\Domain\SerialAllocator;
use Modules\Invoicing\Domain\VatCalculator;

uses(RefreshDatabase::class);

afterEach(fn () => TenantContext::clear());

function issuedInvoice(): Invoice
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
        'description' => 'X',
        'quantity' => '1.0000',
        'unit' => 'pc',
        'unit_price' => '1120.00',
        'line_total' => '1120.0000',
        'vat_classification' => 'vatable',
    ]);

    return (new IssueInvoiceAction(new SerialAllocator, new VatCalculator, new EisPayloadBuilderV1))
        ->execute($invoice->id, (string) Str::uuid());
}

it('marks an issued invoice VOIDED, appends event, retains serial', function () {
    $invoice = issuedInvoice();
    $originalSerial = $invoice->serial_number;

    (new VoidInvoiceAction)->execute($invoice->id, 'Customer cancellation request');

    $invoice->refresh();
    expect($invoice->status)->toBe('voided')
        ->and($invoice->voided_at)->not->toBeNull()
        ->and($invoice->void_reason)->toBe('Customer cancellation request')
        ->and($invoice->serial_number)->toBe($originalSerial);

    expect(InvoiceEvent::where('invoice_id', $invoice->id)->where('event_type', 'VOIDED')->count())
        ->toBe(1)
        ->and(InvoiceEvent::where('invoice_id', $invoice->id)->where('event_type', 'ISSUED')->count())
        ->toBe(1);
});

it('does NOT free the original serial for reuse', function () {
    $seller = Seller::factory()->create();
    TenantContext::set($seller->id);
    $branch = Branch::factory()->for($seller)->create();
    $buyer = Buyer::factory()->for($seller)->create();

    $action = new IssueInvoiceAction(new SerialAllocator, new VatCalculator, new EisPayloadBuilderV1);

    $first = makeAndIssue($branch, $buyer, $action);
    expect($first->serial_number)->toBe(1);

    (new VoidInvoiceAction)->execute($first->id, 'Reason long enough.');

    $second = makeAndIssue($branch, $buyer, $action);
    expect($second->serial_number)->toBe(2);
});

it('rejects a second void on the same invoice', function () {
    $invoice = issuedInvoice();
    $voider = new VoidInvoiceAction;
    $voider->execute($invoice->id, 'First void reason here');

    expect(fn () => $voider->execute($invoice->id, 'Second void reason here'))
        ->toThrow(InvoiceNotIssuableException::class);
});

it('rejects a void with reason shorter than 10 characters', function () {
    $invoice = issuedInvoice();
    expect(fn () => (new VoidInvoiceAction)->execute($invoice->id, 'too short'))
        ->toThrow(InvoiceNotIssuableException::class);
});

it('rejects voiding a draft invoice', function () {
    $seller = Seller::factory()->create();
    TenantContext::set($seller->id);
    $branch = Branch::factory()->for($seller)->create();
    /** @var Invoice $draft */
    $draft = Invoice::create([
        'branch_id' => $branch->id,
        'document_type' => 'sales_invoice',
        'vat_mode' => 'inclusive',
        'status' => 'draft',
        'currency' => 'PHP',
    ]);

    expect(fn () => (new VoidInvoiceAction)->execute($draft->id, 'A reason long enough.'))
        ->toThrow(InvoiceNotIssuableException::class);
});

function makeAndIssue($branch, $buyer, IssueInvoiceAction $action): Invoice
{
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
        'description' => 'X',
        'quantity' => '1.0000',
        'unit' => 'pc',
        'unit_price' => '1120.00',
        'line_total' => '1120.0000',
        'vat_classification' => 'vatable',
    ]);

    return $action->execute($invoice->id, (string) Str::uuid());
}
