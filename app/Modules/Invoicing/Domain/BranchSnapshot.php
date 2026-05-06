<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

final readonly class BranchSnapshot
{
    public function __construct(
        public string $code,
        public string $name,
        public string $address,
    ) {}
}
