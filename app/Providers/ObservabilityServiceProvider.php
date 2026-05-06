<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Centralizes auth gates for the observability dashboards (Horizon, Pulse,
 * Telescope). All three are gated to platform admins only — never exposed
 * publicly. The list of admin emails is configurable via OBSERVABILITY_ADMINS
 * (comma-separated). In local, `local` env users can always view.
 */
class ObservabilityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('viewHorizon', $this->adminCheck());
        Gate::define('viewPulse', $this->adminCheck());
        Gate::define('viewTelescope', $this->adminCheck());
    }

    /**
     * @return Closure(?User): bool
     */
    private function adminCheck(): Closure
    {
        return function (?User $user): bool {
            if (app()->environment('local')) {
                return true;
            }

            if ($user === null) {
                return false;
            }

            $admins = array_filter(array_map('trim', explode(',', (string) config('mds.observability_admins', ''))));

            return in_array($user->email, $admins, true);
        };
    }
}
