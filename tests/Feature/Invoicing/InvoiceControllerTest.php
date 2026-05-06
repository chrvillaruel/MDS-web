<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Branch;
use Modules\Identity\Domain\Role;
use Modules\Identity\Domain\Seller;
use Modules\Invoicing\Domain\Buyer;
use Modules\Invoicing\Domain\Invoice;

uses(RefreshDatabase::class);

afterEach(fn () => TenantContext::clear());

function loginUserForSeller(): array
{
    $seller = Seller::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['current_seller_id' => $seller->id]);
    $user->sellers()->attach($seller->id, ['role' => Role::Owner->value]);

    $branch = Branch::factory()->for($seller)->create();
    test()->actingAs($user);

    return [$user, $seller, $branch];
}

it('the create page renders for an authenticated, set-up seller', function () {
    [$user, $seller, $branch] = loginUserForSeller();

    $response = $this->get('/invoices/create');

    $response->assertOk();
});

it('store creates and issues a manual invoice end-to-end', function () {
    Storage::fake('local');
    [$user, $seller, $branch] = loginUserForSeller();

    $key = (string) Str::uuid();

    $response = $this->post('/invoices', [
        'branch_id' => $branch->id,
        'document_type' => 'sales_invoice',
        'vat_mode' => 'inclusive',
        'idempotency_key' => $key,
        'buyer' => [
            'id' => null,
            'registered_name' => 'Walk-in customer',
            'email' => 'walkin@example.com',
            'tin' => null,
            'address' => null,
        ],
        'lines' => [[
            'description' => 'Phone case',
            'quantity' => '1',
            'unit' => 'pc',
            'unit_price' => '1120.00',
            'vat_classification' => 'vatable',
        ]],
    ]);

    /** @var Invoice $invoice */
    $invoice = Invoice::first();
    expect($invoice->status)->toBe('issued')
        ->and($invoice->serial_number)->toBe(1)
        ->and($invoice->idempotency_key)->toBe($key)
        ->and((float) $invoice->total_amount)->toEqual(1120.00);

    $response->assertRedirect("/invoices/{$invoice->id}");
    expect(Buyer::count())->toBe(1)
        ->and(Buyer::first()->email)->toBe('walkin@example.com')
        ->and(Storage::disk('local')->exists($invoice->pdf_path))->toBeTrue();
});

it('rejects an invoice with no lines', function () {
    [$user, $seller, $branch] = loginUserForSeller();

    $this->post('/invoices', [
        'branch_id' => $branch->id,
        'document_type' => 'sales_invoice',
        'vat_mode' => 'inclusive',
        'idempotency_key' => (string) Str::uuid(),
        'buyer' => ['id' => null, 'registered_name' => 'Walk-in'],
        'lines' => [],
    ])->assertSessionHasErrors(['lines']);

    expect(Invoice::count())->toBe(0);
});

it('voids an issued invoice via the controller', function () {
    Storage::fake('local');
    [$user, $seller, $branch] = loginUserForSeller();

    $this->post('/invoices', [
        'branch_id' => $branch->id,
        'document_type' => 'sales_invoice',
        'vat_mode' => 'inclusive',
        'idempotency_key' => (string) Str::uuid(),
        'buyer' => ['id' => null, 'registered_name' => 'Walk-in'],
        'lines' => [[
            'description' => 'Phone case', 'quantity' => '1', 'unit' => 'pc',
            'unit_price' => '500.00', 'vat_classification' => 'vatable',
        ]],
    ]);

    /** @var Invoice $invoice */
    $invoice = Invoice::first();

    $this->post("/invoices/{$invoice->id}/void", [
        'reason' => 'Wrong amount entered by cashier; reissuing.',
    ])->assertRedirect();

    $invoice->refresh();
    expect($invoice->status)->toBe('voided')
        ->and($invoice->void_reason)->toContain('Wrong amount');
});
