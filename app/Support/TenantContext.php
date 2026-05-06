<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Holds the active seller id for the current request lifecycle. Set by the
 * `SetTenantContext` middleware on web routes; nullable for unauthenticated /
 * cli contexts (in which case no scope or RLS clause is added — the default
 * is "no rows" via RLS, "all rows" via the Eloquent scope).
 */
final class TenantContext
{
    private static ?int $sellerId = null;

    public static function set(?int $sellerId): void
    {
        self::$sellerId = $sellerId;

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("SET LOCAL mds.current_seller_id = '".(string) ($sellerId ?? '')."'");
        }
    }

    public static function sellerId(): ?int
    {
        return self::$sellerId;
    }

    public static function clear(): void
    {
        self::$sellerId = null;
    }
}
