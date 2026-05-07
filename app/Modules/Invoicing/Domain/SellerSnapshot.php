<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

/**
 * Frozen snapshot of seller + branch + buyer state at issuance time.
 * Anything stored on canonical_payload must be sourced from this — never
 * from a fresh query — so subsequent edits to the live records cannot
 * mutate an issued invoice.
 */
final readonly class SellerSnapshot
{
    public function __construct(
        public string $registeredName,
        public string $businessStyle,
        public string $tin,
        public string $branchCode,
        public string $address,
        public string $vatStatus,
        public ?string $birRdoCode,
        public ?string $accreditationNumber,
        public ?string $machineIdentificationNumber,
        public ?string $softwareLicenseNumber,
    ) {}
}
