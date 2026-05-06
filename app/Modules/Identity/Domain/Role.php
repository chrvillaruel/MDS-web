<?php

declare(strict_types=1);

namespace Modules\Identity\Domain;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Cashier = 'cashier';
    case Accountant = 'accountant';
    case ReadOnly = 'read_only';

    public function canIssueInvoices(): bool
    {
        return match ($this) {
            self::Owner, self::Admin, self::Cashier => true,
            self::Accountant, self::ReadOnly => false,
        };
    }

    public function requiresTwoFactor(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            default => false,
        };
    }
}
