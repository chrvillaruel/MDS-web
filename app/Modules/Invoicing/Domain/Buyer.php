<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

use App\Concerns\BelongsToSeller;
use Database\Factories\BuyerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['seller_id', 'tin', 'registered_name', 'business_style', 'address', 'email'])]
class Buyer extends Model
{
    use BelongsToSeller;

    /** @use HasFactory<BuyerFactory> */
    use HasFactory;

    protected static function newFactory(): BuyerFactory
    {
        return BuyerFactory::new();
    }
}
