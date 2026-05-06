<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

use App\Concerns\BelongsToSeller;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'seller_id', 'branch_id', 'buyer_id', 'document_type', 'status',
    'serial_number', 'reset_counter', 'eis_unique_id', 'canonical_payload',
    'subtotal', 'vat_amount', 'total_amount', 'currency', 'issued_at',
    'voided_at', 'source', 'source_reference',
])]
class Invoice extends Model
{
    use BelongsToSeller;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'canonical_payload' => 'array',
            'subtotal' => 'decimal:4',
            'vat_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * @return HasMany<InvoiceEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(InvoiceEvent::class);
    }
}
