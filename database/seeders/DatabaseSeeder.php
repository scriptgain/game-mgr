<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo data for the local stack. Ordered because each step leans on the last:
 * you cannot place a server without a node, or build one without a template.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            AccountSeeder::class,
            CatalogueSeeder::class,
            InfrastructureSeeder::class,
            ServerSeeder::class,
            ActivitySeeder::class,
        ]);
    }
}
