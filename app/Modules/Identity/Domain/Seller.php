<?php

declare(strict_types=1);

namespace Modules\Identity\Domain;

use Database\Factories\SellerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    /** @use HasFactory<SellerFactory> */
    use HasFactory;

    protected static function newFactory(): SellerFactory
    {
        return SellerFactory::new();
    }

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
