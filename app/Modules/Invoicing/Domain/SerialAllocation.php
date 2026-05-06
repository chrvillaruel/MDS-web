<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

final readonly class SerialAllocation
{
    public function __construct(
        public int $number,
        public int $resetCounter,
    ) {}
}
