<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

/**
 * V1 canonical payload — RMO 24-2023 published format (PRD §3.2 C-3.2.2).
 *
 * Outputs are deterministic: same input bytes → same output bytes. Tests use
 * golden files to lock this in (a payload built today must equal the same
 * payload rebuilt years from now from the same snapshot).
 */
final class EisPayloadBuilderV1 implements EisPayloadBuilder
{
    public function version(): string
    {
        return 'v1';
    }

    public function build(EisBuildContext $ctx): array
    {
        $calc = $ctx->calculation;

        return [
            'schema_version' => $this->version(),
            'eis_unique_id' => $ctx->eisUniqueId,
            'invoice_type' => $ctx->invoiceType->value,
            'serial' => [
                'number' => $ctx->serial->number,
                'reset_counter' => $ctx->serial->resetCounter,
                'formatted' => $this->formatSerial($ctx->serial),
            ],
            'issued_at' => $ctx->issuedAt->format(DATE_ATOM),
            'vat_mode' => $ctx->vatMode->value,
            'currency' => 'PHP',
            'seller' => [
                'registered_name' => $ctx->seller->registeredName,
                'business_style' => $ctx->seller->businessStyle,
                'tin' => $ctx->seller->tin,
                'branch_code' => $ctx->seller->branchCode,
                'address' => $ctx->seller->address,
                'vat_status' => $ctx->seller->vatStatus,
                'bir_rdo_code' => $ctx->seller->birRdoCode,
                'accreditation_number' => $ctx->seller->accreditationNumber,
                'machine_identification_number' => $ctx->seller->machineIdentificationNumber,
                'software_license_number' => $ctx->seller->softwareLicenseNumber,
            ],
            'branch' => [
                'code' => $ctx->branch->code,
                'name' => $ctx->branch->name,
                'address' => $ctx->branch->address,
            ],
            'buyer' => $ctx->buyer === null ? null : [
                'registered_name' => $ctx->buyer->registeredName,
                'business_style' => $ctx->buyer->businessStyle,
                'tin' => $ctx->buyer->tin,
                'address' => $ctx->buyer->address,
                'email' => $ctx->buyer->email,
            ],
            'lines' => array_map(
                fn (LineCalculation $line) => [
                    'line_number' => $line->lineNumber,
                    'description' => $line->description,
                    'quantity' => $this->scaledToDecimalString($line->quantityScaled, 4),
                    'unit' => $line->unit,
                    'unit_price' => $line->unitPrice->toDecimalString(),
                    'classification' => $line->classification->value,
                    'line_net' => $line->lineNet->toDecimalString(),
                    'line_vat' => $line->lineVat->toDecimalString(),
                    'line_total' => $line->lineTotal->toDecimalString(),
                ],
                $calc->lines,
            ),
            'totals' => [
                'vatable_sales' => $calc->vatableSales->toDecimalString(),
                'vat_exempt_sales' => $calc->vatExemptSales->toDecimalString(),
                'zero_rated_sales' => $calc->zeroRatedSales->toDecimalString(),
                'vat_amount' => $calc->vatAmount->toDecimalString(),
                'subtotal' => $calc->subtotal->toDecimalString(),
                'total_amount' => $calc->totalAmount->toDecimalString(),
                'has_zero_rated_sale' => $calc->hasZeroRatedSale,
            ],
            'supersedes' => $ctx->supersedesEisUniqueId === null ? null : [
                'eis_unique_id' => $ctx->supersedesEisUniqueId,
                'serial' => $ctx->supersedesSerial,
            ],
            'note' => $ctx->note,
        ];
    }

    private function formatSerial(SerialAllocation $serial): string
    {
        return sprintf('%010d-%02d', $serial->number, $serial->resetCounter);
    }

    private function scaledToDecimalString(int $scaled, int $decimals): string
    {
        $divisor = 10 ** $decimals;
        $sign = $scaled < 0 ? '-' : '';
        $abs = abs($scaled);
        $whole = intdiv($abs, $divisor);
        $frac = $abs % $divisor;

        return sprintf("%s%d.%0{$decimals}d", $sign, $whole, $frac);
    }
}
