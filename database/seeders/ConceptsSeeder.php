<?php

namespace Database\Seeders;

use App\Models\Concept;
use Illuminate\Database\Seeder;

class ConceptsSeeder extends Seeder
{
    public function run(): void
    {
        $concepts = [
            ['id' => 1, 'code' => 'CAP',  'name' => 'CAPITAL',          'type' => 'ingreso', 'status' => 'active'],
            ['id' => 2, 'code' => 'INT',  'name' => 'INTERES',          'type' => 'ingreso', 'status' => 'active'],
            ['id' => 3, 'code' => 'MOR',  'name' => 'MORA',             'type' => 'ingreso', 'status' => 'active'],
            ['id' => 4, 'code' => 'DES',  'name' => 'DESEMBOLSO',       'type' => 'egreso',  'status' => 'active'],
            ['id' => 5, 'code' => 'GAS',  'name' => 'GASTOS OPERATIVOS','type' => 'egreso',  'status' => 'active'],
            ['id' => 6, 'code' => 'OTR',  'name' => 'OTROS',            'type' => 'egreso',  'status' => 'active'],
        ];

        foreach ($concepts as $data) {
            Concept::updateOrCreate(
                ['id' => $data['id']],
                [
                    'code'   => $data['code'],
                    'name'   => $data['name'],
                    'type'   => $data['type'],
                    'status' => $data['status'],
                ]
            );
        }
    }
}
