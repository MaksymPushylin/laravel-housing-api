<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_can_be_created_and_processed(): void
    {
        Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $payload = [
            'supplier' => 'supplier-a',
            'external_import_id' => 'test-import-001',
            'sent_at' => '2026-09-04 12:00:00',
            'offers' => [
                [
                    'external_id' => 'offer-001',
                    'property' => [
                        'code' => 'property-001',
                        'name' => 'Central Apartment',
                        'city' => 'Kyiv',
                    ],
                    'check_in' => '2026-10-01',
                    'check_out' => '2026-10-05',
                    'max_guests' => 2,
                    'price' => 120.00,
                    'currency' => 'USD',
                    'available_units' => 3,
                    'expires_at' => '2026-09-30 23:59:59',
                ],
            ],
        ];

        $response = $this->postJson('/api/imports', $payload);

        $response
            ->assertStatus(202)
            ->assertJsonStructure([
                'id',
                'status',
            ]);

        $this->assertDatabaseHas('imports', [
            'supplier_id' => Supplier::where('code', 'supplier-a')->value('id'),
            'external_import_id' => 'test-import-001',
            'status' => 'completed',
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);

        $this->assertDatabaseHas('properties', [
            'code' => 'property-001',
            'name' => 'Central Apartment',
            'city' => 'Kyiv',
        ]);

        $this->assertDatabaseHas('offers', [
            'supplier_id' => Supplier::where('code', 'supplier-a')->value('id'),
            'external_id' => 'offer-001',
            'price' => 120.00,
            'currency' => 'USD',
            'available_units' => 3,
        ]);
    }

    public function test_import_requires_existing_supplier(): void
    {
        $payload = [
            'supplier' => 'unknown-supplier',
            'external_import_id' => 'test-import-002',
            'sent_at' => '2026-09-04 12:00:00',
            'offers' => [
                [
                    'external_id' => 'offer-002',
                    'property' => [
                        'code' => 'property-002',
                        'name' => 'Test Apartment',
                        'city' => 'Kyiv',
                    ],
                    'check_in' => '2026-10-01',
                    'check_out' => '2026-10-05',
                    'max_guests' => 2,
                    'price' => 100.00,
                    'currency' => 'USD',
                    'available_units' => 1,
                    'expires_at' => '2026-09-30 23:59:59',
                ],
            ],
        ];

        $response = $this->postJson('/api/imports', $payload);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['supplier']);

        $this->assertDatabaseCount('imports', 0);
    }

    public function test_duplicate_import_returns_existing_import(): void
    {
        Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $payload = [
            'supplier' => 'supplier-a',
            'external_import_id' => 'test-import-003',
            'sent_at' => '2026-09-04 12:00:00',
            'offers' => [
                [
                    'external_id' => 'offer-003',
                    'property' => [
                        'code' => 'property-003',
                        'name' => 'Test Apartment',
                        'city' => 'Kyiv',
                    ],
                    'check_in' => '2026-10-01',
                    'check_out' => '2026-10-05',
                    'max_guests' => 2,
                    'price' => 150.00,
                    'currency' => 'USD',
                    'available_units' => 2,
                    'expires_at' => '2026-09-30 23:59:59',
                ],
            ],
        ];

        $firstResponse = $this->postJson('/api/imports', $payload);

        $firstResponse
            ->assertStatus(202)
            ->assertJsonStructure(['id', 'status']);

        $secondResponse = $this->postJson('/api/imports', $payload);

        $secondResponse
            ->assertStatus(202)
            ->assertJson([
                'id' => $firstResponse->json('id'),
                'status' => 'completed',
            ]);

        $this->assertDatabaseCount('imports', 1);
        $this->assertDatabaseCount('offers', 1);

        $this->assertDatabaseHas('imports', [
            'external_import_id' => 'test-import-003',
            'status' => 'completed',
            'processed_offers' => 1,
        ]);

        $this->assertDatabaseHas('offers', [
            'external_id' => 'offer-003',
        ]);
    }

    public function test_existing_offer_is_updated_by_new_import(): void
    {
        Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $firstPayload = [
            'supplier' => 'supplier-a',
            'external_import_id' => 'test-import-004',
            'sent_at' => '2026-09-04 12:00:00',
            'offers' => [
                [
                    'external_id' => 'offer-004',
                    'property' => [
                        'code' => 'property-004',
                        'name' => 'Old Apartment Name',
                        'city' => 'Kyiv',
                    ],
                    'check_in' => '2026-10-01',
                    'check_out' => '2026-10-05',
                    'max_guests' => 2,
                    'price' => 150.00,
                    'currency' => 'USD',
                    'available_units' => 2,
                    'expires_at' => '2026-09-30 23:59:59',
                ],
            ],
        ];

        $this->postJson('/api/imports', $firstPayload)
            ->assertStatus(202);

        $offer = Offer::where('external_id', 'offer-004')->firstOrFail();

        $this->assertDatabaseCount('offers', 1);

        $secondPayload = [
            'supplier' => 'supplier-a',
            'external_import_id' => 'test-import-005',
            'sent_at' => '2026-09-04 13:00:00',
            'offers' => [
                [
                    'external_id' => 'offer-004',
                    'property' => [
                        'code' => 'property-004',
                        'name' => 'Updated Apartment Name',
                        'city' => 'Lviv',
                    ],
                    'check_in' => '2026-11-01',
                    'check_out' => '2026-11-05',
                    'max_guests' => 4,
                    'price' => 99.99,
                    'currency' => 'EUR',
                    'available_units' => 5,
                    'expires_at' => '2026-10-31 23:59:59',
                ],
            ],
        ];

        $this->postJson('/api/imports', $secondPayload)
            ->assertStatus(202);

        $this->assertDatabaseCount('offers', 1);

        $offer->refresh();

        $this->assertSame('offer-004', $offer->external_id);
        $this->assertSame('99.99', $offer->price);
        $this->assertSame('EUR', $offer->currency);
        $this->assertSame(5, $offer->available_units);

        $this->assertSame(
            'test-import-005',
            $offer->import->external_import_id
        );

        $this->assertDatabaseHas('properties', [
            'code' => 'property-004',
            'name' => 'Updated Apartment Name',
            'city' => 'Lviv',
        ]);
    }

    public function test_import_status_can_be_retrieved(): void
    {
        $supplier = Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'test-import-status',
            'sent_at' => '2026-09-04 12:00:00',
            'status' => 'completed',
            'total_offers' => 3,
            'processed_offers' => 3,
            'completed_at' => '2026-09-04 12:01:00',
        ]);

        $response = $this->getJson("/api/imports/{$import->id}");

        $response
            ->assertOk()
            ->assertJsonPath('id', $import->id)
            ->assertJsonPath('supplier', 'supplier-a')
            ->assertJsonPath('external_import_id', 'test-import-status')
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('total_offers', 3)
            ->assertJsonPath('processed_offers', 3);
    }
}
