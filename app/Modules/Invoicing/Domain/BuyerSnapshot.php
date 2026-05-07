<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

final readonly class BuyerSnapshot
{
    public function __construct(
        public ?string $registeredName,
        public ?string $businessStyle,
        public ?string $tin,
        public ?string $address,
        public ?string $email,
    ) {}
}
