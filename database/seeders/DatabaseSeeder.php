<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Demo\DemoDataService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds a demo account with SYNTHETIC demonstration products and reviews.
     * Refuses to run in production unless REVIEWLENS_ALLOW_DEMO_SEED=true.
     */
    public function run(): void
    {
        if (app()->isProduction() && ! env('REVIEWLENS_ALLOW_DEMO_SEED')) {
            $this->command?->warn('Skipping demo seed in production (set REVIEWLENS_ALLOW_DEMO_SEED=true to override).');

            return;
        }

        $user = User::firstOrCreate(
            ['email' => 'demo@reviewlens.test'],
            ['name' => 'Demo User', 'password' => 'password'],
        );

        app(DemoDataService::class)->seedFor($user);
    }
}
