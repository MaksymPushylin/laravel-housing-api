<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::factory()->create([
            'code' => 'supplier-a',
            'name' => 'Supplier A',
        ]);

        Supplier::factory()->create([
            'code' => 'supplier-b',
            'name' => 'Supplier B',
        ]);
    }
}
