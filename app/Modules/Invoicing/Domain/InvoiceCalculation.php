<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

/**
 * The result of running a list of LineInputs through the VatCalculator.
 *
 * Per PRD TR-6.5.5: invoice totals are the sum of rounded line values, never
 * recomputed from grand totals. The footer buckets are also sums of the
 * matching line values, not separate computations.
 */
final readonly class InvoiceCalculation
{
    /**
     * @param  list<LineCalculation>  $lines
     */
    public function __construct(
        public array $lines,
        public Money $vatableSales,
        public Money $vatExemptSales,
        public Money $zeroRatedSales,
        public Money $vatAmount,
        public Money $subtotal,
        public Money $totalAmount,
        public bool $hasZeroRatedSale,
    ) {}
}
