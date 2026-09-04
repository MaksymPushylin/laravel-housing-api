<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

class ImportController extends Controller
{
    public function store(StoreImportRequest $request): JsonResponse
    {
        $supplier = Supplier::where(
            'code',
            $request->string('supplier')
        )->firstOrFail();

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => $request->string('external_import_id'),
            'sent_at' => $request->date('sent_at'),
            'status' => 'pending',
            'total_offers' => count($request->input('offers')),
        ]);

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
        ], 202);
    }
}
