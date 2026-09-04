<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+1 week', '+1 month');
        $checkOut = (clone $checkIn)->modify('+'.fake()->numberBetween(2, 7).' days');

        return [
            'external_id' => fake()->unique()->uuid(),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'max_guests' => fake()->numberBetween(1, 6), 
            'price' => fake()->randomFloat(2, 50, 500),
            'currency' => 'USD',
            'available_units' => fake()->numberBetween(1, 5),
            'expires_at' => fake()->dateTimeBetween('+1 day', '+2 months'),
        ];
    }
}
