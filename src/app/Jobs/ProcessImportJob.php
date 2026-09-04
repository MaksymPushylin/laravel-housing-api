<?php

namespace App\Jobs;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;

class ProcessImportJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Import $import,
        public array $offers,
    ) {
    }

    public function handle(): void
    {
        $this->import->update([
            'status' => 'processing',
            'error' => null,
            'completed_at' => null,
        ]);

        try {
            foreach ($this->offers as $offerData) {
                DB::transaction(function () use ($offerData) {
                    $property = Property::updateOrCreate(
                        [
                            'code' => $offerData['property']['code'],
                        ],
                        [
                            'name' => $offerData['property']['name'],
                            'city' => $offerData['property']['city'],
                        ],
                    );

                    Offer::updateOrCreate(
                        [
                            'supplier_id' => $this->import->supplier_id,
                            'external_id' => $offerData['external_id'],
                        ],
                        [
                            'import_id' => $this->import->id,
                            'property_id' => $property->id,
                            'check_in' => $offerData['check_in'],
                            'check_out' => $offerData['check_out'],
                            'max_guests' => $offerData['max_guests'],
                            'price' => $offerData['price'],
                            'currency' => $offerData['currency'],
                            'available_units' => $offerData['available_units'],
                            'expires_at' => $offerData['expires_at'],
                        ],
                    );

                    $this->import->increment('processed_offers');
                });
            }

            $this->import->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $this->import->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
