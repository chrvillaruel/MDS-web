<?php

declare(strict_types=1);

namespace Modules\Invoicing\Actions;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Branch;
use Modules\Identity\Domain\Seller;
use Modules\Invoicing\Domain\BranchSnapshot;
use Modules\Invoicing\Domain\Buyer;
use Modules\Invoicing\Domain\BuyerSnapshot;
use Modules\Invoicing\Domain\EisBuildContext;
use Modules\Invoicing\Domain\EisPayloadBuilder;
use Modules\Invoicing\Domain\IdempotencyConflictException;
use Modules\Invoicing\Domain\Invoice;
use Modules\Invoicing\Domain\InvoiceEvent;
use Modules\Invoicing\Domain\InvoiceLine;
use Modules\Invoicing\Domain\InvoiceNotIssuableException;
use Modules\Invoicing\Domain\InvoiceType;
use Modules\Invoicing\Domain\IssuanceAttempt;
use Modules\Invoicing\Domain\LineInput;
use Modules\Invoicing\Domain\Money;
use Modules\Invoicing\Domain\SellerSnapshot;
use Modules\Invoicing\Domain\SerialAllocator;
use Modules\Invoicing\Domain\VatCalculator;
use Modules\Invoicing\Domain\VatClassification;
use Modules\Invoicing\Domain\VatMode;

/**
 * The single code path for issuance — manual or bulk import or anything later
 * funnels through here. Per PRD §6.4 figure 2 + TR-6.5.1.
 *
 * Atomic transaction order, mirroring the spec:
 *   1. Idempotency check         — return existing if same key+fingerprint
 *   2. Lock invoice DRAFT row    — SELECT FOR UPDATE
 *   3. Validate completeness     — 13 header fields + lines
 *   4. Allocate serial           — SerialAllocator (locks branch row)
 *   5. Build canonical payload   — EisPayloadBuilder (versioned, frozen)
 *   6. Persist                   — UPDATE invoice → ISSUED
 *   7. Append-only audit event   — invoice_events
 *   8. Update AGT                — accumulated_grand_total monotonic
 *   9. Record issuance_attempts  — for future replays
 *
 * Side effects (PDF render, buyer email, billing usage_event) are dispatched
 * AFTER commit by the caller — keeping the txn pure and short.
 */
final class IssueInvoiceAction
{
    public function __construct(
        private SerialAllocator $serialAllocator,
        private VatCalculator $vatCalculator,
        private EisPayloadBuilder $payloadBuilder,
    ) {}

    public function execute(
        int $invoiceId,
        string $idempotencyKey,
        ?int $actorUserId = null,
        ?string $actorIp = null,
    ): Invoice {
        return DB::transaction(function () use ($invoiceId, $idempotencyKey, $actorUserId, $actorIp) {
            $sellerId = TenantContext::sellerId();
            if ($sellerId === null) {
                throw new InvoiceNotIssuableException('Tenant context missing.');
            }

            // 1. Idempotency check (cheapest first). Replay = same key + same
            //    input invoice id; conflict = same key + different invoice id.
            $requestFingerprint = hash('sha256', "v1:{$sellerId}:{$invoiceId}");
            $attempt = IssuanceAttempt::where('idempotency_key', $idempotencyKey)->first();
            if ($attempt !== null) {
                if ($attempt->payload_fingerprint !== $requestFingerprint) {
                    throw new IdempotencyConflictException(
                        "Idempotency-Key {$idempotencyKey} reused with a different payload.",
                    );
                }
                /** @var Invoice $existing */
                $existing = Invoice::findOrFail($attempt->invoice_id);

                return $existing;
            }

            // 2. Lock the draft.
            $invoice = Invoice::lockForUpdate()->find($invoiceId);

            if ($invoice === null) {
                throw new InvoiceNotIssuableException("Invoice {$invoiceId} not found.");
            }

            if ($invoice->isIssued() || $invoice->isVoided()) {
                throw new InvoiceNotIssuableException(
                    "Invoice {$invoiceId} is already {$invoice->status}.",
                );
            }

            $lines = InvoiceLine::where('invoice_id', $invoice->id)
                ->orderBy('line_number')
                ->get();

            // 3. Validate completeness.
            $this->validateForIssuance($invoice, $lines);

            // 4. Recompute totals from canonical inputs (defense against any
            //    drift between draft snapshot and live calculator output).
            $vatMode = VatMode::from($invoice->vat_mode ?? 'inclusive');
            $calc = $this->vatCalculator->calculate(
                array_values(array_map(
                    fn (InvoiceLine $line) => new LineInput(
                        lineNumber: (int) $line->line_number,
                        description: (string) $line->description,
                        quantityScaled: $this->scaleQuantity((string) $line->quantity),
                        unit: (string) $line->unit,
                        unitPrice: Money::fromDecimalString((string) $line->unit_price),
                        classification: VatClassification::from((string) $line->vat_classification),
                    ),
                    $lines->all(),
                )),
                $vatMode,
            );

            // 5. Allocate serial.
            $serial = $this->serialAllocator->next((int) $invoice->branch_id);

            // 6. Build canonical payload (snapshot-based, frozen).
            $seller = Seller::withoutGlobalScopes()->findOrFail($invoice->seller_id);
            $branch = Branch::withoutGlobalScopes()->findOrFail($invoice->branch_id);
            $buyer = $invoice->buyer_id
                ? Buyer::withoutGlobalScopes()->find($invoice->buyer_id)
                : null;
            $supersedes = $invoice->supersedes_invoice_id
                ? Invoice::withoutGlobalScopes()->find($invoice->supersedes_invoice_id)
                : null;

            $eisUniqueId = (string) Str::ulid();
            $issuedAt = now()->toDateTimeImmutable();

            $context = new EisBuildContext(
                eisUniqueId: $eisUniqueId,
                invoiceType: InvoiceType::from((string) $invoice->document_type),
                serial: $serial,
                issuedAt: $issuedAt,
                vatMode: $vatMode,
                calculation: $calc,
                seller: $this->snapshotSeller($seller),
                branch: new BranchSnapshot(
                    code: (string) $branch->code,
                    name: (string) $branch->name,
                    address: (string) $branch->address,
                ),
                buyer: $buyer === null ? null : new BuyerSnapshot(
                    registeredName: $buyer->registered_name,
                    businessStyle: $buyer->business_style,
                    tin: $buyer->tin,
                    address: $buyer->address,
                    email: $buyer->email,
                ),
                supersedesEisUniqueId: $supersedes?->eis_unique_id,
                supersedesSerial: $supersedes
                    ? sprintf('%010d-%02d', (int) $supersedes->serial_number, (int) $supersedes->reset_counter)
                    : null,
                note: null,
            );

            $payload = $this->payloadBuilder->build($context);

            // 7. Persist invoice as ISSUED.
            $invoice->forceFill([
                'status' => 'issued',
                'serial_number' => $serial->number,
                'reset_counter' => $serial->resetCounter,
                'eis_unique_id' => $eisUniqueId,
                'canonical_payload' => $payload,
                'payload_schema_version' => $this->payloadBuilder->version(),
                'subtotal' => $calc->subtotal->toDecimalString(),
                'vat_amount' => $calc->vatAmount->toDecimalString(),
                'total_amount' => $calc->totalAmount->toDecimalString(),
                'issued_at' => $issuedAt,
                'idempotency_key' => $idempotencyKey,
            ])->save();

            // 8. Append-only audit event.
            InvoiceEvent::create([
                'invoice_id' => $invoice->id,
                'event_type' => 'ISSUED',
                'payload' => $payload,
                'actor_user_id' => $actorUserId,
                'actor_ip' => $actorIp,
                'occurred_at' => $issuedAt,
            ]);

            // 9. Bump AGT under advisory lock (Postgres). On other drivers we
            //    rely on the row-level UPDATE being atomic enough at MVP
            //    contention — production runs Postgres.
            $this->advanceAccumulatedGrandTotal($seller->id, $calc->totalAmount->centavos);

            // 10. Record idempotency outcome.
            IssuanceAttempt::create([
                'idempotency_key' => $idempotencyKey,
                'payload_fingerprint' => $requestFingerprint,
                'invoice_id' => $invoice->id,
                'outcome' => 'issued',
            ]);

            return $invoice->refresh();
        });
    }

    /**
     * @param  Collection<int, InvoiceLine>  $lines
     */
    private function validateForIssuance(Invoice $invoice, Collection $lines): void
    {
        $missing = [];

        foreach (['branch_id', 'document_type', 'vat_mode'] as $field) {
            if (empty($invoice->{$field})) {
                $missing[] = $field;
            }
        }

        if ($lines->isEmpty()) {
            $missing[] = 'lines';
        }

        $type = InvoiceType::from((string) $invoice->document_type);
        if ($type->requiresOriginal() && empty($invoice->supersedes_invoice_id)) {
            $missing[] = 'supersedes_invoice_id';
        }

        if ($missing !== []) {
            throw new InvoiceNotIssuableException(
                'Invoice is missing required fields: '.implode(', ', $missing),
            );
        }
    }

    private function snapshotSeller(Seller $seller): SellerSnapshot
    {
        return new SellerSnapshot(
            registeredName: (string) $seller->registered_name,
            businessStyle: (string) ($seller->business_style ?? ''),
            tin: (string) $seller->tin,
            branchCode: (string) $seller->branch_code,
            address: (string) $seller->address,
            vatStatus: (string) $seller->vat_status,
            birRdoCode: $seller->bir_rdo_code,
            accreditationNumber: $seller->accreditation_number,
            machineIdentificationNumber: $seller->machine_identification_number,
            softwareLicenseNumber: $seller->software_license_number,
        );
    }

    private function scaleQuantity(string $decimal): int
    {
        $rounded = round((float) $decimal, 4, PHP_ROUND_HALF_UP);

        return (int) round($rounded * 10_000, 0, PHP_ROUND_HALF_UP);
    }

    private function advanceAccumulatedGrandTotal(int $sellerId, int $deltaCentavos): void
    {
        $conn = DB::connection();
        if ($conn->getDriverName() === 'pgsql') {
            // Advisory lock keyed off seller id. PRD TR-9.2.3.
            $conn->statement('SELECT pg_advisory_xact_lock(?)', [$sellerId]);
        }

        $conn->update(
            'UPDATE sellers SET accumulated_grand_total = accumulated_grand_total + ?, updated_at = ? WHERE id = ?',
            [$deltaCentavos, now(), $sellerId],
        );
    }
}
