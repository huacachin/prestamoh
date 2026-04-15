<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HeadquartersSeeder extends Seeder
{
    public function run(): void
    {
        $headquarters = [
            'Huacachín',
        ];

        foreach ($headquarters as $hq) {
            DB::table('headquarters')->updateOrInsert(
                ['name' => $hq],
                ['status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
