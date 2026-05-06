<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

/**
 * Renders an invoice (issued or voided) to PDF bytes.
 *
 * Production binding: BrowsershotInvoicePdfRenderer (headless Chromium).
 * Test binding: HtmlInvoicePdfRenderer (returns the HTML body so tests can
 * assert content without needing a Chromium binary).
 *
 * See ADR-005 for why we chose Browsershot over a programmatic PDF library.
 */
interface InvoicePdfRenderer
{
    public function render(Invoice $invoice): string;

    /**
     * Filesystem suffix for the rendered artifact (e.g. "pdf" or "html").
     * Lets the job path the file with the right extension.
     */
    public function extension(): string;
}
