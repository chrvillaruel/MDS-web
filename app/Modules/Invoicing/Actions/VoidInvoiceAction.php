<?php

declare(strict_types=1);

namespace Modules\Invoicing\Actions;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Modules\Invoicing\Domain\Invoice;
use Modules\Invoicing\Domain\InvoiceEvent;
use Modules\Invoicing\Domain\InvoiceNotIssuableException;
use Modules\Invoicing\Jobs\RenderInvoicePdf;

/**
 * Voids an issued invoice. Per PRD §6 US-6.3, FR-6.2.7.
 *
 * Rules (regulatory):
 *   - Only ISSUED invoices can be voided.
 *   - Voiding an already-VOIDED invoice is a clear error (not a second event).
 *   - The original is RETAINED with status VOIDED — never deleted.
 *   - The original serial number is NOT freed for reuse.
 *   - A VOIDED audit event is appended; the original ISSUED event remains.
 *   - The PDF is re-rendered with the VOIDED watermark + reason.
 *   - Reason must be ≥ 10 characters (FR-6.2.7).
 */
final class VoidInvoiceAction
{
    public function execute(
        int $invoiceId,
        string $reason,
        ?int $actorUserId = null,
        ?string $actorIp = null,
    ): Invoice {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvoiceNotIssuableException(
                'Void reason must be at least 10 characters.',
            );
        }

        $invoice = DB::transaction(function () use ($invoiceId, $reason, $actorUserId, $actorIp) {
            /** @var Invoice|null $invoice */
            $invoice = Invoice::lockForUpdate()->find($invoiceId);

            if ($invoice === null) {
                throw new InvoiceNotIssuableException("Invoice {$invoiceId} not found.");
            }

            if (! $invoice->isIssued()) {
                throw new InvoiceNotIssuableException(
                    "Only issued invoices can be voided (current status: {$invoice->status}).",
                );
            }

            $now = now();

            $invoice->forceFill([
                'status' => 'voided',
                'voided_at' => $now,
                'void_reason' => $reason,
                'voided_by_user_id' => $actorUserId,
            ])->save();

            InvoiceEvent::create([
                'invoice_id' => $invoice->id,
                'event_type' => 'VOIDED',
                'payload' => [
                    'reason' => $reason,
                    'voided_at' => $now->toIso8601String(),
                    'voided_by_user_id' => $actorUserId,
                ],
                'actor_user_id' => $actorUserId,
                'actor_ip' => $actorIp,
                'occurred_at' => $now,
            ]);

            return $invoice->refresh();
        });

        // Re-render PDF with the VOIDED watermark.
        Bus::dispatchSync(new RenderInvoicePdf($invoice->id, $invoice->seller_id));

        return $invoice->refresh();
    }
}
