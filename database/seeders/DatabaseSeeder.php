<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ServiceSeeder::class, PersonnelSeeder::class);
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(PharmaStockThresholdSeeder::class);
        $this->call(\Database\Seeders\PharmaSmartThresholdSeeder::class);
    }
}
