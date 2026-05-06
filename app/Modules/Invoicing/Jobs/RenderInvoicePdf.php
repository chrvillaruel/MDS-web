<?php

declare(strict_types=1);

namespace Modules\Invoicing\Jobs;

use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableJob;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Modules\Invoicing\Domain\Invoice;
use Modules\Invoicing\Domain\InvoicePdfRenderer;

/**
 * Renders the invoice artifact and persists `pdf_path` + `pdf_rendered_at`.
 *
 * Manual issuance dispatches sync (low latency on the first PDF the seller
 * sees). Bulk import dispatches async to keep batches throughput-bound rather
 * than render-bound.
 *
 * Per PRD §6 TR-6.5.4 + §11.3 (render budget < 2s p95).
 */
final class RenderInvoicePdf implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use QueueableJob;
    use SerializesModels;

    public function __construct(
        public int $invoiceId,
        public int $sellerId,
    ) {}

    public function handle(InvoicePdfRenderer $renderer): void
    {
        $previous = TenantContext::sellerId();
        TenantContext::set($this->sellerId);

        try {
            /** @var Invoice|null $invoice */
            $invoice = Invoice::find($this->invoiceId);
            if ($invoice === null) {
                return;
            }

            $bytes = $renderer->render($invoice);
            $extension = $renderer->extension();

            $path = sprintf(
                'invoices/%d/%s.%s',
                $invoice->seller_id,
                $invoice->eis_unique_id,
                $extension,
            );

            Storage::disk('local')->put($path, $bytes);

            $invoice->forceFill([
                'pdf_path' => $path,
                'pdf_rendered_at' => now(),
            ])->save();
        } finally {
            TenantContext::set($previous);
        }
    }
}
