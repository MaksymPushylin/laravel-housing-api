<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_can_be_created_and_decrements_available_units(): void
    {
        $offer = $this->createOffer([
            'available_units' => 2,
        ]);

        $response = $this->postJson(
            "/api/offers/{$offer->id}/reservations",
            [
                'client_reference' => 'booking-001',
                'customer_name' => 'John Doe',
                'customer_email' => 'john@example.com',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('offer_id', $offer->id)
            ->assertJsonPath('client_reference', 'booking-001');

        $this->assertDatabaseHas('reservations', [
            'offer_id' => $offer->id,
            'client_reference' => 'booking-001',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'available_units' => 1,
        ]);
    }

    public function test_reservation_cannot_be_created_when_no_units_are_available(): void
    {
        $offer = $this->createOffer([
            'available_units' => 0,
        ]);

        $response = $this->postJson(
            "/api/offers/{$offer->id}/reservations",
            [
                'client_reference' => 'booking-002',
                'customer_name' => 'John Doe',
                'customer_email' => 'john@example.com',
            ]
        );

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Offer is no longer available.',
            ]);

        $this->assertDatabaseMissing('reservations', [
            'client_reference' => 'booking-002',
        ]);

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'available_units' => 0,
        ]);
    }

    public function test_duplicate_client_reference_returns_conflict_and_does_not_decrement_units(): void
    {
        $offer = $this->createOffer([
            'available_units' => 2,
        ]);

        $payload = [
            'client_reference' => 'booking-003',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
        ];

        $this->postJson(
            "/api/offers/{$offer->id}/reservations",
            $payload
        )->assertCreated();

        $response = $this->postJson(
            "/api/offers/{$offer->id}/reservations",
            $payload
        );

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Client reference already exists.',
            ]);

        $this->assertDatabaseCount('reservations', 1);

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'available_units' => 1,
        ]);
    }

    private function createOffer(array $attributes = []): Offer
    {
        $supplier = Supplier::factory()->create();

        $property = Property::factory()->create();

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => fake()->unique()->uuid(),
            'sent_at' => now(),
            'status' => 'completed',
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);

        /** @var Offer $offer */
        $offer = Offer::factory()->create(array_merge([
            'supplier_id' => $supplier->id,
            'import_id' => $import->id,
            'property_id' => $property->id,
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 120.00,
            'currency' => 'USD',
            'available_units' => 2,
            'expires_at' => '2026-10-10 23:59:59',
        ], $attributes));

        return $offer;
    }

    public function test_second_reservation_cannot_book_when_last_unit_is_already_reserved(): void
    {
        $offer = $this->createOffer([
            'available_units' => 1,
        ]);

        $firstReservation = $this->postJson(
            "/api/offers/{$offer->id}/reservations",
            [
                'client_reference' => 'booking-concurrent-001',
                'customer_name' => 'John Doe',
                'customer_email' => 'john@example.com',
            ]
        );

        $secondReservation = $this->postJson(
            "/api/offers/{$offer->id}/reservations",
            [
                'client_reference' => 'booking-concurrent-002',
                'customer_name' => 'Jane Doe',
                'customer_email' => 'jane@example.com',
            ]
        );

        $firstReservation->assertCreated();
        $secondReservation->assertStatus(409);

        $this->assertDatabaseCount('reservations', 1);

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'available_units' => 0,
        ]);
    }
}
