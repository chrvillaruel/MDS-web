<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\InvoicePolicy;
use App\Policies\SellerPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Domain\Seller;
use Modules\Invoicing\Domain\Invoice;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Seller::class, SellerPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
    }
}
