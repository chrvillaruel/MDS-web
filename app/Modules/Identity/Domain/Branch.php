<?php

declare(strict_types=1);

namespace Modules\Identity\Domain;

use App\Concerns\BelongsToSeller;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['seller_id', 'code', 'name', 'address', 'max_serial'])]
class Branch extends Model
{
    use BelongsToSeller;

    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_serial' => 'integer',
            'reset_counter' => 'integer',
            'max_serial' => 'integer',
        ];
    }
}
