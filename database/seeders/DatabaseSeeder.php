<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionCatalogSeeder::class,
            HeadquartersSeeder::class,
            UsersSeeder::class,
            ConceptsSeeder::class,
        ]);
    }
}
