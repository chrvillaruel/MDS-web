<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\InvoicePolicy;
use App\Policies\SellerPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Domain\Seller;
use Modules\Invoicing\Domain\EisPayloadBuilder;
use Modules\Invoicing\Domain\EisPayloadBuilderV1;
use Modules\Invoicing\Domain\Invoice;
use Modules\Invoicing\Domain\InvoicePdfRenderer;
use Modules\Invoicing\Infrastructure\HtmlInvoicePdfRenderer;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EisPayloadBuilder::class, EisPayloadBuilderV1::class);
        $this->app->bind(InvoicePdfRenderer::class, HtmlInvoicePdfRenderer::class);
    }

    public function boot(): void
    {
        Gate::policy(Seller::class, SellerPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
    }
}
