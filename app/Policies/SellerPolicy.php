<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Modules\Identity\Domain\Seller;

final class SellerPolicy
{
    public function view(User $user, Seller $seller): bool
    {
        return $this->isMember($user, $seller);
    }

    public function update(User $user, Seller $seller): bool
    {
        return $this->isMember($user, $seller, ['owner', 'admin']);
    }

    /**
     * @param  list<string>|null  $roles
     */
    private function isMember(User $user, Seller $seller, ?array $roles = null): bool
    {
        $query = $user->sellers()->whereKey($seller->getKey());

        if ($roles !== null) {
            $query->wherePivotIn('role', $roles);
        }

        return $query->exists();
    }
}
