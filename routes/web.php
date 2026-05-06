<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Identity\Domain\Seller;
use Modules\Identity\Http\Controllers\SetupController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', function () {
        $user = Auth::user();
        $seller = $user?->current_seller_id !== null
            ? Seller::find($user->current_seller_id)
            : null;

        return Inertia::render('Dashboard/Index', [
            'seller' => $seller ? [
                'id' => $seller->id,
                'registered_name' => $seller->registered_name,
                'tin' => $seller->tin,
                'vat_status' => $seller->vat_status,
                'setup_completed_at' => $seller->setup_completed_at?->toIso8601String(),
            ] : null,
        ]);
    })->name('dashboard');

    Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
    Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');
});
