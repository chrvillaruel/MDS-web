<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::Voided => 'Voided',
        };
    }
}
