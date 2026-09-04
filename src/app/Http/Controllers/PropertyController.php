<?php

namespace App\Http\Controllers;

use App\Http\Requests\PropertyIndexRequest;
use App\Models\Offer;
use App\Http\Resources\PropertyResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PropertyController extends Controller
{
    public function index(PropertyIndexRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();

        $offers = Offer::query()
            ->where('check_in', $data['check_in'])
            ->where('check_out', $data['check_out'])
            ->where('max_guests', '>=', $data['guests'])
            ->where('available_units', '>', 0)
            ->where('expires_at', '>', now());

        if (!empty($data['city'])) {
            $offers->whereHas('property', function ($query) use ($data) {
                $query->where('city', $data['city']);
            });
        }

        $rankedOffers = $offers->select([
            'offers.*',
            DB::raw('ROW_NUMBER() OVER (
            PARTITION BY property_id
            ORDER BY price ASC, id ASC
        ) AS row_num'),
        ]);

        $query = DB::table('properties')
            ->joinSub($rankedOffers, 'ranked_offers', function ($join) {
                $join->on('properties.id', '=', 'ranked_offers.property_id')
                    ->where('ranked_offers.row_num', 1);
            })
            ->select([
                'properties.id as property_id',
                'properties.code as property_code',
                'properties.name as property_name',
                'properties.city as property_city',

                'ranked_offers.id as offer_id',
                'ranked_offers.supplier_id',
                'ranked_offers.external_id',
                'ranked_offers.check_in',
                'ranked_offers.check_out',
                'ranked_offers.max_guests',
                'ranked_offers.price',
                'ranked_offers.currency',
                'ranked_offers.available_units',
                'ranked_offers.expires_at',
            ]);

        $sortBy = $data['sort_by'] ?? 'price';
        $sortDir = $data['sort_dir'] ?? 'asc';

        $sortColumn = match ($sortBy) {
            'name' => 'properties.name',
            'city' => 'properties.city',
            default => 'ranked_offers.price',
        };

        $query->orderBy($sortColumn, $sortDir);

        $perPage = $data['per_page'] ?? 20;

        $properties = $query->paginate($perPage)->withQueryString();

        return PropertyResource::collection($properties);
    }
}
