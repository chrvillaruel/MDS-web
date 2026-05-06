<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

use DateTimeImmutable;

final readonly class EisBuildContext
{
    public function __construct(
        public string $eisUniqueId,
        public InvoiceType $invoiceType,
        public SerialAllocation $serial,
        public DateTimeImmutable $issuedAt,
        public VatMode $vatMode,
        public InvoiceCalculation $calculation,
        public SellerSnapshot $seller,
        public BranchSnapshot $branch,
        public ?BuyerSnapshot $buyer,
        public ?string $supersedesEisUniqueId,
        public ?string $supersedesSerial,
        public ?string $note,
    ) {}
}
