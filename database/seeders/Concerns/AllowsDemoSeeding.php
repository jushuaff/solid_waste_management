<?php
namespace Database\Seeders\Concerns;

trait AllowsDemoSeeding
{
    protected function assertDemoSeedingAllowed(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('swm.demo_seed_enabled', false)) {
            throw new \RuntimeException(
                'Demo data includes test users with the intentionally weak password 1234. '
                .'Run it ONLY in local/testing with SWM_DEMO_SEED=true. Never run on public or production instances.'
            );
        }
    }
}
