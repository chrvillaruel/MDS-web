<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

/**
 * Whether the user-entered amount is VAT-inclusive (gross) or
 * VAT-exclusive (net). Set per-invoice.
 */
enum VatMode: string
{
    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';
}
