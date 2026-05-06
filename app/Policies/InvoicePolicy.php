<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Modules\Invoicing\Domain\Invoice;

final class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->current_seller_id === $invoice->seller_id
            && $user->sellers()->whereKey($invoice->seller_id)->exists();
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice)
            && $user->sellers()
                ->whereKey($invoice->seller_id)
                ->wherePivotIn('role', ['owner', 'admin', 'cashier'])
                ->exists();
    }
}
