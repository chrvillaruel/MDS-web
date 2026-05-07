<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

enum InvoiceType: string
{
    case SalesInvoice = 'sales_invoice';
    case OfficialReceipt = 'official_receipt';
    case CreditNote = 'credit_note';
    case DebitNote = 'debit_note';

    public function label(): string
    {
        return match ($this) {
            self::SalesInvoice => 'Sales Invoice',
            self::OfficialReceipt => 'Official Receipt',
            self::CreditNote => 'Credit Note',
            self::DebitNote => 'Debit Note',
        };
    }

    /**
     * Credit / debit notes must reference an original invoice.
     */
    public function requiresOriginal(): bool
    {
        return $this === self::CreditNote || $this === self::DebitNote;
    }
}
