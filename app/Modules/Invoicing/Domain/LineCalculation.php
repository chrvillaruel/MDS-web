<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

final readonly class LineCalculation
{
    public function __construct(
        public int $lineNumber,
        public string $description,
        public int $quantityScaled,
        public string $unit,
        public Money $unitPrice,
        public VatClassification $classification,
        /** the gross amount printed on the line (subtotal + vat for that line) */
        public Money $lineTotal,
        /** the net portion of $lineTotal */
        public Money $lineNet,
        /** the VAT portion of $lineTotal (zero for non-vatable classifications) */
        public Money $lineVat,
    ) {}
}
