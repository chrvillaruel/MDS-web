<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Scopes\SellerScope;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Domain\Seller;

/**
 * Auto-injects `WHERE seller_id = current_seller_id` on every query and
 * sets `seller_id` on every create. Combined with Postgres RLS (see
 * `add_postgres_constraints_and_rls` migration) this gives defense in depth.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToSeller
{
    public static function bootBelongsToSeller(): void
    {
        static::addGlobalScope(new SellerScope);

        static::creating(function ($model): void {
            if (empty($model->seller_id) && ($sellerId = TenantContext::sellerId()) !== null) {
                $model->seller_id = $sellerId;
            }
        });
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
