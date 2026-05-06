<?php

declare(strict_types=1);

namespace Modules\Invoicing\Infrastructure;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Modules\Invoicing\Domain\Invoice;
use Modules\Invoicing\Domain\InvoicePdfRenderer;

/**
 * Renders the invoice Blade template to plain HTML.
 *
 * MVP-default binding. Production will rebind to a Browsershot-backed
 * implementation once the Chromium runtime is provisioned on Laravel Cloud
 * (ADR-005 follow-up). The HTML output is regulator-faithful — same template
 * Browsershot consumes — so artifact content is identical between bindings.
 */
final class HtmlInvoicePdfRenderer implements InvoicePdfRenderer
{
    public function __construct(private ViewFactory $views) {}

    public function render(Invoice $invoice): string
    {
        return (string) $this->views->make('pdfs.invoice', [
            'invoice' => $invoice,
            'payload' => $invoice->canonical_payload,
        ])->render();
    }

    public function extension(): string
    {
        return 'html';
    }
}
