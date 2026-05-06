<?php

declare(strict_types=1);

use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Branch;
use Modules\Identity\Domain\Seller;
use Modules\Invoicing\Actions\IssueInvoiceAction;
use Modules\Invoicing\Domain\Buyer;
use Modules\Invoicing\Domain\EisBuildContext;
use Modules\Invoicing\Domain\EisPayloadBuilder;
use Modules\Invoicing\Domain\EisPayloadBuilderV1;
use Modules\Invoicing\Domain\IdempotencyConflictException;
use Modules\Invoicing\Domain\Invoice;
use Modules\Invoicing\Domain\InvoiceEvent;
use Modules\Invoicing\Domain\InvoiceLine;
use Modules\Invoicing\Domain\InvoiceNotIssuableException;
use Modules\Invoicing\Domain\IssuanceAttempt;
use Modules\Invoicing\Domain\SerialAllocator;
use Modules\Invoicing\Domain\VatCalculator;

uses(RefreshDatabase::class);

afterEach(function () {
    TenantContext::clear();
});

function makeAction(): IssueInvoiceAction
{
    return new IssueInvoiceAction(
        new SerialAllocator,
        new VatCalculator,
        new EisPayloadBuilderV1,
    );
}

function makeDraftInvoice(Branch $branch, Buyer $buyer, array $lines = []): Invoice
{
    /** @var Invoice $invoice */
    $invoice = Invoice::create([
        'branch_id' => $branch->id,
        'buyer_id' => $buyer->id,
        'document_type' => 'sales_invoice',
        'vat_mode' => 'inclusive',
        'status' => 'draft',
        'currency' => 'PHP',
        'source' => 'manual',
    ]);

    if ($lines === []) {
        $lines = [[
            'description' => 'Widget',
            'quantity' => '1.0000',
            'unit' => 'pc',
            'unit_price' => '1120.00',
            'vat_classification' => 'vatable',
        ]];
    }

    foreach ($lines as $i => $line) {
        $line['line_number'] = $i + 1;
        $line['line_total'] = number_format(
            (float) ($line['quantity'] ?? '1') * (float) ($line['unit_price'] ?? '0'),
            4, '.', '',
        );
        InvoiceLine::create([...$line, 'invoice_id' => $invoice->id]);
    }

    return $invoice->refresh();
}

function setupTenant(): array
{
    $seller = Seller::factory()->create(['accumulated_grand_total' => 0]);
    TenantContext::set($seller->id);
    $branch = Branch::factory()->for($seller)->create();
    $buyer = Buyer::factory()->for($seller)->create();

    return [$seller, $branch, $buyer];
}

it('issues a draft to ISSUED with serial, eis_unique_id, and frozen payload', function () {
    [$seller, $branch, $buyer] = setupTenant();
    $invoice = makeDraftInvoice($branch, $buyer);

    $issued = makeAction()->execute($invoice->id, (string) Str::uuid());

    expect($issued->status)->toBe('issued')
        ->and($issued->serial_number)->toBe(1)
        ->and($issued->reset_counter)->toBe(0)
        ->and($issued->eis_unique_id)->not->toBeNull()
        ->and($issued->canonical_payload)->not->toBeNull()
        ->and($issued->payload_schema_version)->toBe('v1')
        ->and((float) $issued->total_amount)->toEqual(1120.00)
        ->and((float) $issued->vat_amount)->toEqual(120.00);
});

it('writes exactly one ISSUED audit event per issuance', function () {
    [$seller, $branch, $buyer] = setupTenant();
    $invoice = makeDraftInvoice($branch, $buyer);

    makeAction()->execute($invoice->id, (string) Str::uuid());

    expect(InvoiceEvent::where('invoice_id', $invoice->id)->where('event_type', 'ISSUED')->count())
        ->toBe(1);
});

it('advances the accumulated_grand_total by the total in centavos', function () {
    [$seller, $branch, $buyer] = setupTenant();
    $invoice = makeDraftInvoice($branch, $buyer);

    makeAction()->execute($invoice->id, (string) Str::uuid());

    $seller->refresh();
    expect((int) $seller->accumulated_grand_total)->toBe(112_000);
});

it('idempotency: same key + same payload returns the same invoice and writes one attempt', function () {
    [$seller, $branch, $buyer] = setupTenant();
    $invoice = makeDraftInvoice($branch, $buyer);
    $key = (string) Str::uuid();

    $first = makeAction()->execute($invoice->id, $key);

    // Re-issue the same draft — but it's already ISSUED. The replay should
    // short-circuit on idempotency lookup (same key) and return the same row.
    for ($i = 0; $i < 9; $i++) {
        $again = makeAction()->execute($invoice->id, $key);
        expect($again->id)->toBe($first->id)
            ->and($again->serial_number)->toBe($first->serial_number);
    }

    expect(IssuanceAttempt::where('idempotency_key', $key)->count())->toBe(1)
        ->and(Invoice::where('seller_id', $seller->id)->count())->toBe(1)
        ->and(InvoiceEvent::where('event_type', 'ISSUED')->count())->toBe(1);
});

it('idempotency: same key + different payload throws IdempotencyConflictException', function () {
    [$seller, $branch, $buyer] = setupTenant();
    $a = makeDraftInvoice($branch, $buyer);
    $b = makeDraftInvoice($branch, $buyer, [[
        'description' => 'Different',
        'quantity' => '2.0000',
        'unit' => 'pc',
        'unit_price' => '500.00',
        'vat_classification' => 'vatable',
    ]]);
    $key = (string) Str::uuid();

    makeAction()->execute($a->id, $key);

    expect(fn () => makeAction()->execute($b->id, $key))
        ->toThrow(IdempotencyConflictException::class);
});

it('refuses to issue an already-issued invoice', function () {
    [$seller, $branch, $buyer] = setupTenant();
    $invoice = makeDraftInvoice($branch, $buyer);

    makeAction()->execute($invoice->id, (string) Str::uuid());

    expect(fn () => makeAction()->execute($invoice->id, (string) Str::uuid()))
        ->toThrow(InvoiceNotIssuableException::class);
});

it('refuses to issue an empty invoice (no lines)', function () {
    [$seller, $branch, $buyer] = setupTenant();
    /** @var Invoice $invoice */
    $invoice = Invoice::create([
        'branch_id' => $branch->id,
        'buyer_id' => $buyer->id,
        'document_type' => 'sales_invoice',
        'vat_mode' => 'inclusive',
        'status' => 'draft',
        'currency' => 'PHP',
    ]);

    expect(fn () => makeAction()->execute($invoice->id, (string) Str::uuid()))
        ->toThrow(InvoiceNotIssuableException::class);

    // Serial must NOT have been consumed.
    $branch->refresh();
    expect($branch->current_serial)->toBe(0);
});

it('rolls back atomically when an inner step throws — no serial consumed, status remains draft', function () {
    [$seller, $branch, $buyer] = setupTenant();
    $invoice = makeDraftInvoice($branch, $buyer);

    // Inject a payload builder that throws to simulate a mid-transaction failure.
    $action = new IssueInvoiceAction(
        new SerialAllocator,
        new VatCalculator,
        new class implements EisPayloadBuilder
        {
            public function version(): string
            {
                return 'v-broken';
            }

            public function build(EisBuildContext $context): array
            {
                throw new RuntimeException('mid-txn failure');
            }
        },
    );

    expect(fn () => $action->execute($invoice->id, (string) Str::uuid()))
        ->toThrow(RuntimeException::class);

    $invoice->refresh();
    $branch->refresh();
    expect($invoice->status)->toBe('draft')
        ->and($invoice->serial_number)->toBeNull()
        ->and($invoice->canonical_payload)->toBeNull()
        ->and($branch->current_serial)->toBe(0);
});

it('100 sequential issuances on the same branch produce 100 distinct monotonic serials with zero duplicates', function () {
    [$seller, $branch, $buyer] = setupTenant();

    $serials = [];
    for ($i = 0; $i < 100; $i++) {
        $invoice = makeDraftInvoice($branch, $buyer);
        $issued = makeAction()->execute($invoice->id, (string) Str::uuid());
        $serials[] = $issued->serial_number;
    }

    expect($serials)->toHaveCount(100)
        ->and(array_unique($serials))->toHaveCount(100)
        ->and(min($serials))->toBe(1)
        ->and(max($serials))->toBe(100);
});

it('issues a credit note that supersedes an existing invoice', function () {
    [$seller, $branch, $buyer] = setupTenant();
    $original = makeDraftInvoice($branch, $buyer);
    $action = makeAction();
    $action->execute($original->id, (string) Str::uuid());
    $original->refresh();

    /** @var Invoice $cn */
    $cn = Invoice::create([
        'branch_id' => $branch->id,
        'buyer_id' => $buyer->id,
        'supersedes_invoice_id' => $original->id,
        'document_type' => 'credit_note',
        'vat_mode' => 'inclusive',
        'status' => 'draft',
        'currency' => 'PHP',
    ]);
    InvoiceLine::create([
        'invoice_id' => $cn->id,
        'line_number' => 1,
        'description' => 'Refund',
        'quantity' => '1.0000',
        'unit' => 'pc',
        'unit_price' => '1120.00',
        'line_total' => '1120.0000',
        'vat_classification' => 'vatable',
    ]);

    $issued = $action->execute($cn->id, (string) Str::uuid());

    expect($issued->document_type)->toBe('credit_note')
        ->and($issued->canonical_payload['invoice_type'])->toBe('credit_note')
        ->and($issued->canonical_payload['supersedes']['eis_unique_id'])
        ->toBe($original->eis_unique_id);
});
