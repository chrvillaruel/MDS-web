<?php

declare(strict_types=1);

use Modules\Invoicing\Domain\BranchSnapshot;
use Modules\Invoicing\Domain\BuyerSnapshot;
use Modules\Invoicing\Domain\EisBuildContext;
use Modules\Invoicing\Domain\EisPayloadBuilderV1;
use Modules\Invoicing\Domain\InvoiceType;
use Modules\Invoicing\Domain\LineInput;
use Modules\Invoicing\Domain\Money;
use Modules\Invoicing\Domain\SellerSnapshot;
use Modules\Invoicing\Domain\SerialAllocation;
use Modules\Invoicing\Domain\VatCalculator;
use Modules\Invoicing\Domain\VatClassification;
use Modules\Invoicing\Domain\VatMode;

function buildSampleContext(): EisBuildContext
{
    $calc = (new VatCalculator)->calculate(
        [
            new LineInput(1, 'Widget', 20_000, 'pc', Money::fromCentavos(50_000), VatClassification::Vatable),
            new LineInput(2, 'Senior pack', 10_000, 'pc', Money::fromCentavos(30_000), VatClassification::VatExempt),
            new LineInput(3, 'Export', 10_000, 'pc', Money::fromCentavos(100_000), VatClassification::ZeroRated),
        ],
        VatMode::Inclusive,
    );

    return new EisBuildContext(
        eisUniqueId: '01HABCDEF000000000000000000',
        invoiceType: InvoiceType::SalesInvoice,
        serial: new SerialAllocation(42, 0),
        issuedAt: new DateTimeImmutable('2026-06-15T10:30:00+08:00'),
        vatMode: VatMode::Inclusive,
        calculation: $calc,
        seller: new SellerSnapshot(
            registeredName: 'Acme Trading Co.',
            businessStyle: 'Acme',
            tin: '123456789',
            branchCode: '00000',
            address: '123 Rizal Ave, Manila',
            vatStatus: 'vat_registered',
            birRdoCode: '044',
            accreditationNumber: 'ACC-2026-001',
            machineIdentificationNumber: 'MIN-001',
            softwareLicenseNumber: 'SLN-001',
        ),
        branch: new BranchSnapshot(code: 'HQ', name: 'Head Office', address: '123 Rizal Ave'),
        buyer: new BuyerSnapshot(
            registeredName: 'Juan dela Cruz',
            businessStyle: null,
            tin: '987654321',
            address: '5 Mabini St',
            email: 'juan@example.com',
        ),
        supersedesEisUniqueId: null,
        supersedesSerial: null,
        note: null,
    );
}

it('builds a deterministic payload (golden file match)', function () {
    $payload = (new EisPayloadBuilderV1)->build(buildSampleContext());

    $golden = json_decode(
        (string) file_get_contents(__DIR__.'/golden/eis-payload-v1-sample.json'),
        associative: true,
    );

    expect($payload)->toBe($golden);
});

it('records schema_version v1 and a stable formatted serial', function () {
    $payload = (new EisPayloadBuilderV1)->build(buildSampleContext());

    expect($payload['schema_version'])->toBe('v1')
        ->and($payload['serial']['formatted'])->toBe('0000000042-00')
        ->and($payload['totals']['has_zero_rated_sale'])->toBeTrue();
});

it('omits buyer details when buyer is null', function () {
    $base = buildSampleContext();
    $ctx = new EisBuildContext(
        eisUniqueId: $base->eisUniqueId,
        invoiceType: $base->invoiceType,
        serial: $base->serial,
        issuedAt: $base->issuedAt,
        vatMode: $base->vatMode,
        calculation: $base->calculation,
        seller: $base->seller,
        branch: $base->branch,
        buyer: null,
        supersedesEisUniqueId: null,
        supersedesSerial: null,
        note: null,
    );

    $payload = (new EisPayloadBuilderV1)->build($ctx);
    expect($payload['buyer'])->toBeNull();
});

it('records supersedes link for credit/debit notes', function () {
    $base = buildSampleContext();
    $ctx = new EisBuildContext(
        eisUniqueId: '01HZZZZZ000000000000000000',
        invoiceType: InvoiceType::CreditNote,
        serial: new SerialAllocation(43, 0),
        issuedAt: $base->issuedAt,
        vatMode: $base->vatMode,
        calculation: $base->calculation,
        seller: $base->seller,
        branch: $base->branch,
        buyer: $base->buyer,
        supersedesEisUniqueId: $base->eisUniqueId,
        supersedesSerial: '0000000042-00',
        note: 'Refund — wrong size',
    );

    $payload = (new EisPayloadBuilderV1)->build($ctx);
    expect($payload['invoice_type'])->toBe('credit_note')
        ->and($payload['supersedes']['eis_unique_id'])->toBe($base->eisUniqueId)
        ->and($payload['supersedes']['serial'])->toBe('0000000042-00')
        ->and($payload['note'])->toBe('Refund — wrong size');
});

it('produces strings with regulator-friendly two-decimal money fields', function () {
    $payload = (new EisPayloadBuilderV1)->build(buildSampleContext());

    foreach ($payload['lines'] as $line) {
        expect($line['line_net'])->toMatch('/^-?\d+\.\d{2}$/')
            ->and($line['line_vat'])->toMatch('/^-?\d+\.\d{2}$/')
            ->and($line['line_total'])->toMatch('/^-?\d+\.\d{2}$/')
            ->and($line['quantity'])->toMatch('/^-?\d+\.\d{4}$/');
    }

    foreach (['vatable_sales', 'vat_exempt_sales', 'zero_rated_sales', 'vat_amount', 'subtotal', 'total_amount'] as $k) {
        expect($payload['totals'][$k])->toMatch('/^-?\d+\.\d{2}$/');
    }
});
