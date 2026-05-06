<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers;

use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Domain\Branch;
use Modules\Identity\Domain\Role;
use Modules\Identity\Domain\Seller;

final class SetupController
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Setup/Wizard', [
            'defaults' => [
                'registered_name' => $user?->name,
                'branch_code' => '00000',
                'vat_status' => 'vat_registered',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $data = $request->validate([
            'registered_name' => ['required', 'string', 'max:255'],
            'business_style' => ['nullable', 'string', 'max:255'],
            'tin' => ['required', 'string', 'size:9'],
            'branch_code' => ['required', 'string', 'size:5'],
            'address' => ['required', 'string', 'max:1000'],
            'vat_status' => ['required', 'in:vat_registered,non_vat_registered'],
            'bir_rdo_code' => ['nullable', 'string', 'size:3'],
            'first_branch_code' => ['required', 'string', 'max:16'],
            'first_branch_name' => ['required', 'string', 'max:255'],
            'first_branch_address' => ['required', 'string', 'max:1000'],
        ]);

        $seller = DB::transaction(function () use ($data, $user) {
            $seller = Seller::create([
                'registered_name' => $data['registered_name'],
                'business_style' => $data['business_style'] ?? null,
                'tin' => $data['tin'],
                'branch_code' => $data['branch_code'],
                'address' => $data['address'],
                'vat_status' => $data['vat_status'],
                'bir_rdo_code' => $data['bir_rdo_code'] ?? null,
                'setup_completed_at' => now(),
            ]);

            $user->sellers()->attach($seller->id, [
                'role' => Role::Owner->value,
            ]);

            $user->forceFill(['current_seller_id' => $seller->id])->save();

            TenantContext::set($seller->id);

            Branch::create([
                'code' => $data['first_branch_code'],
                'name' => $data['first_branch_name'],
                'address' => $data['first_branch_address'],
            ]);

            return $seller;
        });

        return redirect('/dashboard')
            ->with('success', "Setup complete for {$seller->registered_name}.");
    }
}
