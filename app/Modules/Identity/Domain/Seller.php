<?php

declare(strict_types=1);

namespace Modules\Identity\Domain;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'registered_name',
    'business_style',
    'tin',
    'branch_code',
    'address',
    'vat_status',
    'bir_rdo_code',
    'accreditation_number',
    'machine_identification_number',
    'software_license_number',
    'setup_completed_at',
])]
class Seller extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accumulated_grand_total' => 'integer',
            'accumulated_grand_total_resets' => 'integer',
            'setup_completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
