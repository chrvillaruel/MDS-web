<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

use App\Concerns\BelongsToSeller;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'seller_id', 'invoice_id', 'event_type', 'payload',
    'actor_user_id', 'actor_ip', 'occurred_at',
])]
class InvoiceEvent extends Model
{
    use BelongsToSeller;

    public $timestamps = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
