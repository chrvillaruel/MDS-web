<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

use App\Concerns\BelongsToSeller;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['seller_id', 'idempotency_key', 'payload_fingerprint', 'invoice_id', 'outcome'])]
class IssuanceAttempt extends Model
{
    use BelongsToSeller;
}
