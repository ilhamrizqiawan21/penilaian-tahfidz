<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Data demo dijalankan eksplisit dengan --class=DemoSeeder agar tidak
        // pernah muncul sebagai efek samping migrasi atau deploy.
    }
}
