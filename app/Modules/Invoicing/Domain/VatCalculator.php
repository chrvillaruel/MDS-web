<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

/**
 * Single source of truth for VAT arithmetic in the entire codebase.
 *
 * Per PRD TR-6.5.2 and FR-6.2.4. Pure domain service — no DB, no HTTP, no
 * framework. Inputs are integers (centavos and 4-decimal-scaled quantities)
 * and a VatMode; outputs are integers. Decimal/string conversion belongs to
 * Money, not here.
 *
 * Ed signs off on this class against ReferenceVatCases each release.
 * No VAT math may live anywhere else (Deptrac rule + grep guard).
 */
final class VatCalculator
{
    /** 12% VAT rate (RA 9337). Held as basis points so the int math stays exact. */
    private const VAT_RATE_BPS = 1200;

    private const BPS_DENOMINATOR = 10_000;

    /** Quantity is scaled by 10_000 inside LineInput (4 decimal places). */
    private const QUANTITY_SCALE = 10_000;

    /**
     * @param  list<LineInput>  $lines
     */
    public function calculate(array $lines, VatMode $mode): InvoiceCalculation
    {
        if ($lines === []) {
            throw new InvalidLineException('At least one line is required.');
        }

        $calculated = [];
        $vatableSales = Money::zero();
        $vatExemptSales = Money::zero();
        $zeroRatedSales = Money::zero();
        $vatAmount = Money::zero();

        foreach ($lines as $input) {
            $line = $this->calculateLine($input, $mode);
            $calculated[] = $line;

            switch ($line->classification) {
                case VatClassification::Vatable:
                    $vatableSales = $vatableSales->plus($line->lineNet);
                    $vatAmount = $vatAmount->plus($line->lineVat);
                    break;
                case VatClassification::ZeroRated:
                    $zeroRatedSales = $zeroRatedSales->plus($line->lineNet);
                    break;
                case VatClassification::VatExempt:
                    $vatExemptSales = $vatExemptSales->plus($line->lineNet);
                    break;
            }
        }

        $subtotal = $vatableSales->plus($vatExemptSales)->plus($zeroRatedSales);
        $totalAmount = $subtotal->plus($vatAmount);
        $hasZeroRated = ! $zeroRatedSales->isZero();

        return new InvoiceCalculation(
            lines: $calculated,
            vatableSales: $vatableSales,
            vatExemptSales: $vatExemptSales,
            zeroRatedSales: $zeroRatedSales,
            vatAmount: $vatAmount,
            subtotal: $subtotal,
            totalAmount: $totalAmount,
            hasZeroRatedSale: $hasZeroRated,
        );
    }

    private function calculateLine(LineInput $input, VatMode $mode): LineCalculation
    {
        $this->guardLine($input);

        // qty * price in centavos × 10_000 (because quantity is scaled by 10_000).
        // PRD TR-6.5.5: line-level rounding, half-up.
        $rawCentavosScaled = $input->quantityScaled * $input->unitPrice->centavos;
        $lineGrossCentavos = $this->divRoundHalfUp($rawCentavosScaled, self::QUANTITY_SCALE);

        $lineGross = Money::fromCentavos($lineGrossCentavos);

        if ($input->classification !== VatClassification::Vatable) {
            // Zero-rated and exempt: VAT is zero regardless of mode.
            return new LineCalculation(
                lineNumber: $input->lineNumber,
                description: $input->description,
                quantityScaled: $input->quantityScaled,
                unit: $input->unit,
                unitPrice: $input->unitPrice,
                classification: $input->classification,
                lineTotal: $lineGross,
                lineNet: $lineGross,
                lineVat: Money::zero(),
            );
        }

        if ($mode === VatMode::Inclusive) {
            // vat = total × (12 / 112)  =>  net = total - vat
            // total includes VAT; back-solve for the VAT component.
            $vatCentavos = $this->divRoundHalfUp(
                $lineGross->centavos * self::VAT_RATE_BPS,
                self::BPS_DENOMINATOR + self::VAT_RATE_BPS,
            );

            $vat = Money::fromCentavos($vatCentavos);
            $net = $lineGross->minus($vat);

            return new LineCalculation(
                lineNumber: $input->lineNumber,
                description: $input->description,
                quantityScaled: $input->quantityScaled,
                unit: $input->unit,
                unitPrice: $input->unitPrice,
                classification: $input->classification,
                lineTotal: $lineGross,
                lineNet: $net,
                lineVat: $vat,
            );
        }

        // Exclusive: lineGross is the net; add 12% VAT on top.
        $vatCentavos = $this->divRoundHalfUp(
            $lineGross->centavos * self::VAT_RATE_BPS,
            self::BPS_DENOMINATOR,
        );

        $vat = Money::fromCentavos($vatCentavos);
        $total = $lineGross->plus($vat);

        return new LineCalculation(
            lineNumber: $input->lineNumber,
            description: $input->description,
            quantityScaled: $input->quantityScaled,
            unit: $input->unit,
            unitPrice: $input->unitPrice,
            classification: $input->classification,
            lineTotal: $total,
            lineNet: $lineGross,
            lineVat: $vat,
        );
    }

    private function guardLine(LineInput $input): void
    {
        if ($input->quantityScaled <= 0) {
            throw new InvalidLineException(
                "Line {$input->lineNumber}: quantity must be positive.",
            );
        }
        if ($input->unitPrice->isNegative()) {
            throw new InvalidLineException(
                "Line {$input->lineNumber}: unit price cannot be negative.",
            );
        }
        if (trim($input->description) === '') {
            throw new InvalidLineException(
                "Line {$input->lineNumber}: description is required.",
            );
        }
    }

    /**
     * Integer divide-and-round, half away from zero (PHP_ROUND_HALF_UP).
     * Mirrors round($a/$b, 0, PHP_ROUND_HALF_UP) but stays in integer space.
     */
    private function divRoundHalfUp(int $numerator, int $denominator): int
    {
        $sign = ($numerator < 0) !== ($denominator < 0) ? -1 : 1;
        $absN = abs($numerator);
        $absD = abs($denominator);

        return $sign * intdiv($absN * 2 + $absD, $absD * 2);
    }
}
