<?php

namespace Database\Seeders;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class PropertyOfferSeeder extends Seeder
{
    public function run(): void
    {
        $supplierA = Supplier::where('code', 'supplier-a')->firstOrFail();
        $supplierB = Supplier::where('code', 'supplier-b')->firstOrFail();

        $property1 = Property::factory()->create([
            'code' => 'property-001',
            'name' => 'Central Apartment',
            'city' => 'Kyiv',
        ]);

        $property2 = Property::factory()->create([
            'code' => 'property-002',
            'name' => 'Cozy House',
            'city' => 'Lviv',
        ]);

        $importA = Import::create([
            'supplier_id' => $supplierA->id,
            'external_import_id' => 'seed-import-a',
            'sent_at' => now(),
            'status' => 'completed',
            'total_offers' => 3,
            'processed_offers' => 3,
            'completed_at' => now(),
        ]);

        $importB = Import::create([
            'supplier_id' => $supplierB->id,
            'external_import_id' => 'seed-import-b',
            'sent_at' => now(),
            'status' => 'completed',
            'total_offers' => 3,
            'processed_offers' => 3,
            'completed_at' => now(),
        ]);

        Offer::factory()->create([
            'supplier_id' => $supplierA->id,
            'import_id' => $importA->id,
            'property_id' => $property1->id,
            'external_id' => 'offer-a-001',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(15)->toDateString(),
            'max_guests' => 2,
            'price' => 150.00,
            'currency' => 'USD',
            'available_units' => 2,
            'expires_at' => now()->addDays(20),
        ]);

        Offer::factory()->create([
            'supplier_id' => $supplierB->id,
            'import_id' => $importB->id,
            'property_id' => $property1->id,
            'external_id' => 'offer-b-001',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(15)->toDateString(),
            'max_guests' => 2,
            'price' => 120.00,
            'currency' => 'USD',
            'available_units' => 3,
            'expires_at' => now()->addDays(20),
        ]);

        Offer::factory()->create([
            'supplier_id' => $supplierA->id,
            'import_id' => $importA->id,
            'property_id' => $property1->id,
            'external_id' => 'offer-a-002',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(15)->toDateString(),
            'max_guests' => 2,
            'price' => 180.00,
            'currency' => 'USD',
            'available_units' => 1,
            'expires_at' => now()->addDays(20),
        ]);

        Offer::factory()->create([
            'supplier_id' => $supplierA->id,
            'import_id' => $importA->id,
            'property_id' => $property2->id,
            'external_id' => 'offer-a-003',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(15)->toDateString(),
            'max_guests' => 4,
            'price' => 200.00,
            'currency' => 'USD',
            'available_units' => 2,
            'expires_at' => now()->addDays(20),
        ]);

        Offer::factory()->create([
            'supplier_id' => $supplierB->id,
            'import_id' => $importB->id,
            'property_id' => $property2->id,
            'external_id' => 'offer-b-002',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(15)->toDateString(),
            'max_guests' => 4,
            'price' => 170.00,
            'currency' => 'USD',
            'available_units' => 1,
            'expires_at' => now()->addDays(20),
        ]);

        Offer::factory()->create([
            'supplier_id' => $supplierB->id,
            'import_id' => $importB->id,
            'property_id' => $property2->id,
            'external_id' => 'offer-b-003',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(15)->toDateString(),
            'max_guests' => 2,
            'price' => 160.00,
            'currency' => 'USD',
            'available_units' => 0,
            'expires_at' => now()->addDays(20),
        ]);
    }
}
