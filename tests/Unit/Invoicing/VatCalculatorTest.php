<?php

declare(strict_types=1);

use Modules\Invoicing\Domain\InvalidLineException;
use Modules\Invoicing\Domain\LineInput;
use Modules\Invoicing\Domain\Money;
use Modules\Invoicing\Domain\VatCalculator;
use Modules\Invoicing\Domain\VatClassification;
use Modules\Invoicing\Domain\VatMode;

function line(
    int $n,
    string $desc,
    int $qtyScaled,
    int $unitPriceCentavos,
    VatClassification $cls = VatClassification::Vatable,
): LineInput {
    return new LineInput(
        lineNumber: $n,
        description: $desc,
        quantityScaled: $qtyScaled,
        unit: 'pc',
        unitPrice: Money::fromCentavos($unitPriceCentavos),
        classification: $cls,
    );
}

it('vat-inclusive: ₱1,120 single line yields ₱120 vat, ₱1,000 net', function () {
    $calc = new VatCalculator;

    $result = $calc->calculate(
        [line(1, 'Item', 10_000, 112_000)], // qty 1, price ₱1,120.00
        VatMode::Inclusive,
    );

    expect($result->vatAmount->centavos)->toBe(12_000)
        ->and($result->vatableSales->centavos)->toBe(100_000)
        ->and($result->totalAmount->centavos)->toBe(112_000)
        ->and($result->subtotal->centavos)->toBe(100_000);
});

it('vat-exclusive: ₱1,000 single line yields ₱120 vat, ₱1,120 total', function () {
    $calc = new VatCalculator;

    $result = $calc->calculate(
        [line(1, 'Item', 10_000, 100_000)],
        VatMode::Exclusive,
    );

    expect($result->vatAmount->centavos)->toBe(12_000)
        ->and($result->vatableSales->centavos)->toBe(100_000)
        ->and($result->totalAmount->centavos)->toBe(112_000);
});

it('zero-rated: zero vat, flag set, sale counted in zero-rated bucket', function () {
    $calc = new VatCalculator;

    $result = $calc->calculate(
        [line(1, 'Export', 10_000, 500_000, VatClassification::ZeroRated)],
        VatMode::Inclusive,
    );

    expect($result->vatAmount->isZero())->toBeTrue()
        ->and($result->zeroRatedSales->centavos)->toBe(500_000)
        ->and($result->vatableSales->isZero())->toBeTrue()
        ->and($result->hasZeroRatedSale)->toBeTrue();
});

it('vat-exempt: zero vat, sale counted in exempt bucket', function () {
    $calc = new VatCalculator;

    $result = $calc->calculate(
        [line(1, 'Senior meds', 10_000, 200_000, VatClassification::VatExempt)],
        VatMode::Inclusive,
    );

    expect($result->vatAmount->isZero())->toBeTrue()
        ->and($result->vatExemptSales->centavos)->toBe(200_000)
        ->and($result->hasZeroRatedSale)->toBeFalse();
});

it('mixed invoice: each bucket totals separately, totals are sum of rounded lines', function () {
    $calc = new VatCalculator;

    $result = $calc->calculate(
        [
            line(1, 'A vatable', 10_000, 112_000),                                 // ₱1,120 inc
            line(2, 'B exempt', 10_000, 50_000, VatClassification::VatExempt),     // ₱500
            line(3, 'C zero-rated', 10_000, 300_000, VatClassification::ZeroRated), // ₱3,000
        ],
        VatMode::Inclusive,
    );

    expect($result->vatableSales->centavos)->toBe(100_000)
        ->and($result->vatAmount->centavos)->toBe(12_000)
        ->and($result->vatExemptSales->centavos)->toBe(50_000)
        ->and($result->zeroRatedSales->centavos)->toBe(300_000)
        ->and($result->subtotal->centavos)->toBe(450_000)
        ->and($result->totalAmount->centavos)->toBe(462_000)
        ->and($result->hasZeroRatedSale)->toBeTrue();
});

it('totals are sum of rounded lines, never recomputed from grand total', function () {
    // Two lines that each round to a number whose sum differs from a one-shot
    // round of the grand pre-rounded total. Per PRD TR-6.5.5 we must use the
    // line-sum, not the grand-recompute.
    $calc = new VatCalculator;

    // ₱33.33 inclusive × 3 lines.
    $result = $calc->calculate(
        [
            line(1, 'A', 10_000, 3_333),
            line(2, 'B', 10_000, 3_333),
            line(3, 'C', 10_000, 3_333),
        ],
        VatMode::Inclusive,
    );

    // 3333 × 12 / 112 = 357.107... → rounds to 357 cents per line.
    // 3 × 357 = 1071 (not 1071.32 → 1071).
    expect($result->vatAmount->centavos)->toBe(3 * 357)
        ->and($result->totalAmount->centavos)->toBe(3 * 3_333)
        ->and($result->vatableSales->centavos)->toBe(3 * (3_333 - 357));
});

it('rounds half-up at line level (PRD TR-6.5.5)', function () {
    // Half-cent case must round UP, not down.
    // unit_price 0.005 ≈ 5 micro-centavos; qty 1 → 0 / 1 → but with round-half-up:
    $calc = new VatCalculator;

    // 1 unit × 1 cent VAT-exclusive: 1 × 12% = 0.12 cents.
    // 0.12 cents rounds to 0 with half-up (since 0.12 < 0.5).
    $result = $calc->calculate(
        [line(1, 'tiny', 10_000, 1)],
        VatMode::Exclusive,
    );

    expect($result->vatAmount->centavos)->toBe(0)
        ->and($result->totalAmount->centavos)->toBe(1);

    // 1 unit × 5 cents VAT-exclusive: 5 × 12% = 0.6 cents → rounds to 1 (half-up).
    $result = $calc->calculate(
        [line(1, 'tiny', 10_000, 5)],
        VatMode::Exclusive,
    );

    expect($result->vatAmount->centavos)->toBe(1)
        ->and($result->totalAmount->centavos)->toBe(6);
});

it('quantity > 1 with fractional decimals: 2.5 × ₱100 = ₱250 net', function () {
    $calc = new VatCalculator;

    $result = $calc->calculate(
        [line(1, 'half-dozen', 25_000, 10_000)], // 2.5 × ₱100
        VatMode::Exclusive,
    );

    expect($result->lines[0]->lineNet->centavos)->toBe(25_000)
        ->and($result->lines[0]->lineVat->centavos)->toBe(3_000)
        ->and($result->lines[0]->lineTotal->centavos)->toBe(28_000);
});

it('rejects empty line list', function () {
    $calc = new VatCalculator;
    expect(fn () => $calc->calculate([], VatMode::Inclusive))
        ->toThrow(InvalidLineException::class);
});

it('rejects non-positive quantity', function () {
    $calc = new VatCalculator;
    expect(fn () => $calc->calculate([line(1, 'x', 0, 10_000)], VatMode::Exclusive))
        ->toThrow(InvalidLineException::class);
    expect(fn () => $calc->calculate([line(1, 'x', -10_000, 10_000)], VatMode::Exclusive))
        ->toThrow(InvalidLineException::class);
});

it('rejects negative unit price', function () {
    $calc = new VatCalculator;
    expect(fn () => $calc->calculate(
        [new LineInput(1, 'x', 10_000, 'pc', Money::fromCentavos(-1), VatClassification::Vatable)],
        VatMode::Exclusive,
    ))->toThrow(InvalidLineException::class);
});

it('rejects blank description', function () {
    $calc = new VatCalculator;
    expect(fn () => $calc->calculate([line(1, '   ', 10_000, 10_000)], VatMode::Exclusive))
        ->toThrow(InvalidLineException::class);
});

it('property: any combination of line types produces consistent totals', function () {
    $calc = new VatCalculator;

    $rng = mt_rand(...);
    mt_srand(42);

    for ($trial = 0; $trial < 50; $trial++) {
        $lines = [];
        $count = $rng(1, 8);
        for ($i = 1; $i <= $count; $i++) {
            $cls = match ($rng(0, 2)) {
                0 => VatClassification::Vatable,
                1 => VatClassification::ZeroRated,
                default => VatClassification::VatExempt,
            };
            $lines[] = line($i, "L{$i}", $rng(1_000, 1_000_000), $rng(1, 1_000_000), $cls);
        }

        $mode = $rng(0, 1) === 0 ? VatMode::Inclusive : VatMode::Exclusive;
        $r = $calc->calculate($lines, $mode);

        // Totals are sum of buckets — never drift.
        expect($r->subtotal->centavos)
            ->toBe($r->vatableSales->centavos + $r->vatExemptSales->centavos + $r->zeroRatedSales->centavos)
            ->and($r->totalAmount->centavos)
            ->toBe($r->subtotal->centavos + $r->vatAmount->centavos);

        // Sum of line nets equals subtotal.
        $sumNet = array_sum(array_map(fn ($l) => $l->lineNet->centavos, $r->lines));
        expect($sumNet)->toBe($r->subtotal->centavos);

        // Sum of line vats equals vat amount.
        $sumVat = array_sum(array_map(fn ($l) => $l->lineVat->centavos, $r->lines));
        expect($sumVat)->toBe($r->vatAmount->centavos);
    }
});
