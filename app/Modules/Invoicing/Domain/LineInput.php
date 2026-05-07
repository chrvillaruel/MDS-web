<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

/**
 * One line as the calculator sees it. quantity is allowed up to 4 decimal
 * places (kept as an int in tenths-of-thousandths to keep arithmetic
 * lossless); unit_price is centavos.
 */
final readonly class LineInput
{
    public function __construct(
        public int $lineNumber,
        public string $description,
        /** quantity scaled by 10_000 (4 decimal places) */
        public int $quantityScaled,
        public string $unit,
        public Money $unitPrice,
        public VatClassification $classification,
    ) {}
}
