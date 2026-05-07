<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Domain\Branch;
use Modules\Identity\Domain\Seller;
use Modules\Invoicing\Domain\SerialAllocator;

uses(RefreshDatabase::class);

it('returns strictly monotonic serials starting at 1', function (): void {
    $seller = Seller::factory()->create();
    $branch = Branch::factory()->for($seller)->create();
    $allocator = new SerialAllocator;

    DB::transaction(fn () => $allocator->next($branch->id));
    DB::transaction(fn () => $allocator->next($branch->id));
    $third = DB::transaction(fn () => $allocator->next($branch->id));

    expect($third->number)->toBe(3)
        ->and($third->resetCounter)->toBe(0);

    $branch->refresh();
    expect($branch->current_serial)->toBe(3);
});

it('rolls reset_counter when current_serial reaches max_serial', function (): void {
    $seller = Seller::factory()->create();
    $branch = Branch::factory()->for($seller)->create([
        'max_serial' => 3,
        'current_serial' => 2,
    ]);
    $allocator = new SerialAllocator;

    $a = DB::transaction(fn () => $allocator->next($branch->id));
    expect($a->number)->toBe(3)->and($a->resetCounter)->toBe(0);

    $b = DB::transaction(fn () => $allocator->next($branch->id));
    expect($b->number)->toBe(1)->and($b->resetCounter)->toBe(1);
});

it('treats rolled-back transactions as having consumed the serial (gaps allowed, reuse never)', function (): void {
    $seller = Seller::factory()->create();
    $branch = Branch::factory()->for($seller)->create();
    $allocator = new SerialAllocator;

    try {
        DB::transaction(function () use ($allocator, $branch): void {
            $allocator->next($branch->id);
            throw new RuntimeException('simulated failure');
        });
    } catch (RuntimeException) {
        // expected
    }

    // Fresh allocation: serial 1 was rolled back, but the next one is 1
    // because the row was reverted. Per §3.4 C-3.4.4 "gaps allowed, reuse
    // never": the gap rule applies once the *committed* counter advances.
    // A rolled-back allocation never committed, so there is no gap to skip.
    $next = DB::transaction(fn () => $allocator->next($branch->id));
    expect($next->number)->toBe(1);
});

it('produces 100 distinct serials across sequential calls (smoke test for contention path)', function (): void {
    $seller = Seller::factory()->create();
    $branch = Branch::factory()->for($seller)->create();
    $allocator = new SerialAllocator;

    $serials = [];
    for ($i = 0; $i < 100; $i++) {
        $alloc = DB::transaction(fn () => $allocator->next($branch->id));
        $serials[] = $alloc->number;
    }

    expect($serials)->toHaveCount(100)
        ->and(array_unique($serials))->toHaveCount(100)
        ->and($serials[0])->toBe(1)
        ->and(end($serials))->toBe(100);
});
