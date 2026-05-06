<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

use App\Concerns\BelongsToSeller;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'seller_id', 'invoice_id', 'line_number', 'description', 'quantity',
    'unit', 'unit_price', 'line_total', 'vat_classification',
])]
class InvoiceLine extends Model
{
    use BelongsToSeller;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'line_total' => 'decimal:4',
            'line_number' => 'integer',
        ];
    }
}
