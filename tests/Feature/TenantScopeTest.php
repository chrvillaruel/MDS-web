<?php

declare(strict_types=1);

use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Domain\Branch;
use Modules\Identity\Domain\Seller;

uses(RefreshDatabase::class);

afterEach(function (): void {
    TenantContext::clear();
});

it('SellerScope hides rows from other tenants', function (): void {
    $alpha = Seller::create([
        'registered_name' => 'Alpha',
        'tin' => '111111111',
        'branch_code' => '00000',
        'address' => 'A',
        'vat_status' => 'vat_registered',
    ]);

    $beta = Seller::create([
        'registered_name' => 'Beta',
        'tin' => '222222222',
        'branch_code' => '00000',
        'address' => 'B',
        'vat_status' => 'vat_registered',
    ]);

    Branch::withoutGlobalScopes()->create(['seller_id' => $alpha->id, 'code' => 'A1', 'name' => 'A1', 'address' => 'A']);
    Branch::withoutGlobalScopes()->create(['seller_id' => $beta->id, 'code' => 'B1', 'name' => 'B1', 'address' => 'B']);

    TenantContext::set($alpha->id);
    expect(Branch::pluck('code')->all())->toBe(['A1']);

    TenantContext::set($beta->id);
    expect(Branch::pluck('code')->all())->toBe(['B1']);
});

it('BelongsToSeller auto-fills seller_id on create when tenant is set', function (): void {
    $seller = Seller::create([
        'registered_name' => 'Acme',
        'tin' => '333333333',
        'branch_code' => '00000',
        'address' => 'M',
        'vat_status' => 'vat_registered',
    ]);

    TenantContext::set($seller->id);

    $branch = Branch::create(['code' => 'HQ', 'name' => 'HQ', 'address' => 'M']);

    expect($branch->seller_id)->toBe($seller->id);
});
