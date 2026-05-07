<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

use App\Concerns\BelongsToSeller;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'seller_id', 'branch_id', 'buyer_id', 'supersedes_invoice_id',
    'document_type', 'vat_mode', 'status',
    'serial_number', 'reset_counter', 'eis_unique_id',
    'canonical_payload', 'payload_schema_version',
    'subtotal', 'vat_amount', 'total_amount', 'currency', 'issued_at',
    'voided_at', 'void_reason', 'voided_by_user_id',
    'source', 'source_reference', 'idempotency_key',
    'pdf_path', 'pdf_rendered_at',
])]
/**
 * @property int $id
 * @property int $seller_id
 * @property int $branch_id
 * @property int|null $buyer_id
 * @property int|null $supersedes_invoice_id
 * @property string $document_type
 * @property string $vat_mode
 * @property string $status
 * @property int|null $serial_number
 * @property int $reset_counter
 * @property string|null $eis_unique_id
 * @property array<string, mixed>|null $canonical_payload
 * @property string|null $payload_schema_version
 * @property string $subtotal
 * @property string $vat_amount
 * @property string $total_amount
 * @property string $currency
 * @property Carbon|null $issued_at
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property int|null $voided_by_user_id
 * @property string $source
 * @property string|null $source_reference
 * @property string|null $idempotency_key
 * @property string|null $pdf_path
 * @property Carbon|null $pdf_rendered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
            'pdf_rendered_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
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
