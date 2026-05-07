<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

/**
 * Per-line VAT treatment. Drives VatCalculator output and the VAT-breakdown
 * footer on the PDF (PRD §3.1 field 13).
 */
enum VatClassification: string
{
    case Vatable = 'vatable';
    case ZeroRated = 'zero_rated';
    case VatExempt = 'vat_exempt';

    public function label(): string
    {
        return match ($this) {
            self::Vatable => 'Vatable',
            self::ZeroRated => 'Zero-Rated',
            self::VatExempt => 'VAT-Exempt',
        };
    }
}
