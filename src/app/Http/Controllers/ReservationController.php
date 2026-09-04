<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Models\Offer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function store(StoreReservationRequest $request, Offer $offer): JsonResponse
    {
        $data = $request->validated();

        try {
            $reservation = DB::transaction(function () use ($data, $offer) {
                $offer = Offer::query()
                    ->whereKey($offer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($offer->available_units < 1) {
                    abort(409, 'Offer is no longer available.');
                }

                $offer->decrement('available_units');

                return $offer->reservations()->create([
                    'client_reference' => $data['client_reference'],
                    'customer_name' => $data['customer_name'],
                    'customer_email' => $data['customer_email'],
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            return response()->json([
                'message' => 'Client reference already exists.',
            ], 409);
        }

        return response()->json([
            'id' => $reservation->id,
            'offer_id' => $reservation->offer_id,
            'client_reference' => $reservation->client_reference,
            'customer_name' => $reservation->customer_name,
            'customer_email' => $reservation->customer_email,
            'created_at' => $reservation->created_at,
        ], 201);
    }
}
