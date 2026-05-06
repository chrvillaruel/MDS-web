<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
final class SellerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $sellerId = TenantContext::sellerId();

        if ($sellerId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('seller_id'), $sellerId);
    }
}
