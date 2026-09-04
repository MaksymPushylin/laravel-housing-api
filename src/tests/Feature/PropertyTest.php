<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_cheapest_available_offer_for_each_property(): void
    {
        $supplierA = Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $supplierB = Supplier::factory()->create([
            'code' => 'supplier-b',
        ]);

        $property = Property::factory()->create([
            'code' => 'property-001',
            'name' => 'Central Apartment',
            'city' => 'Kyiv',
        ]);

        $importA = Import::create([
            'supplier_id' => $supplierA->id,
            'external_import_id' => 'test-import-properties-a',
            'sent_at' => '2026-09-04 12:00:00',
            'status' => 'completed',
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);

        $importB = Import::create([
            'supplier_id' => $supplierB->id,
            'external_import_id' => 'test-import-properties-b',
            'sent_at' => '2026-09-04 12:00:00',
            'status' => 'completed',
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);

        Offer::factory()->create([
            'import_id' => $importA->id,
            'supplier_id' => $supplierA->id,
            'property_id' => $property->id,
            'external_id' => 'offer-expensive',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 150.00,
            'currency' => 'USD',
            'available_units' => 2,
            'expires_at' => '2026-10-10 23:59:59',
        ]);

        Offer::factory()->create([
            'import_id' => $importB->id,
            'supplier_id' => $supplierB->id,
            'property_id' => $property->id,
            'external_id' => 'offer-cheapest',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 120.00,
            'currency' => 'USD',
            'available_units' => 3,
            'expires_at' => '2026-10-10 23:59:59',
        ]);


        $response = $this->getJson(
            '/api/properties?check_in=2026-10-01&check_out=2026-10-05&guests=2'
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.0.property.code', 'property-001')
            ->assertJsonPath('data.0.best_offer.external_id', 'offer-cheapest')
            ->assertJsonPath('data.0.best_offer.price', 120);
    }
}
