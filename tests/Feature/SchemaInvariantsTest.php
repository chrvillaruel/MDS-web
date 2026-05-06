<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Domain\Branch;
use Modules\Identity\Domain\Seller;
use Modules\Invoicing\Domain\Invoice;

uses(RefreshDatabase::class);

it('enforces UNIQUE on (seller_id, branch_id, serial_number, reset_counter)', function (): void {
    $seller = Seller::create([
        'registered_name' => 'Acme PH',
        'tin' => '123456789',
        'branch_code' => '00000',
        'address' => 'Manila, PH',
        'vat_status' => 'vat_registered',
    ]);

    $branch = Branch::withoutGlobalScopes()->create([
        'seller_id' => $seller->id,
        'code' => 'HQ',
        'name' => 'Head Office',
        'address' => 'Manila, PH',
    ]);

    Invoice::withoutGlobalScopes()->create([
        'seller_id' => $seller->id,
        'branch_id' => $branch->id,
        'status' => 'issued',
        'serial_number' => 1,
        'reset_counter' => 0,
        'eis_unique_id' => 'eis-001',
        'canonical_payload' => ['v' => 1],
    ]);

    expect(fn () => Invoice::withoutGlobalScopes()->create([
        'seller_id' => $seller->id,
        'branch_id' => $branch->id,
        'status' => 'issued',
        'serial_number' => 1,
        'reset_counter' => 0,
        'eis_unique_id' => 'eis-002',
        'canonical_payload' => ['v' => 2],
    ]))->toThrow(QueryException::class);
});

it('enforces UNIQUE on (tin, branch_code) for sellers', function (): void {
    Seller::create([
        'registered_name' => 'Acme PH',
        'tin' => '123456789',
        'branch_code' => '00001',
        'address' => 'Manila, PH',
        'vat_status' => 'vat_registered',
    ]);

    expect(fn () => Seller::create([
        'registered_name' => 'Acme PH 2',
        'tin' => '123456789',
        'branch_code' => '00001',
        'address' => 'Cebu, PH',
        'vat_status' => 'vat_registered',
    ]))->toThrow(QueryException::class);
});
