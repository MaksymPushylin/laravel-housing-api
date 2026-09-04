<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class ImportController extends Controller
{
    public function store(StoreImportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $supplier = Supplier::where('code', $data['supplier'])->firstOrFail();

        try {
            $import = Import::create([
                'supplier_id' => $supplier->id,
                'external_import_id' => $data['external_import_id'],
                'sent_at' => $data['sent_at'],
                'status' => 'pending',
                'total_offers' => count($data['offers']),
            ]);

            ProcessImportJob::dispatch($import, $data['offers']);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }

            $import = Import::where('supplier_id', $supplier->id)
                ->where('external_import_id', $data['external_import_id'])
                ->firstOrFail();
        }

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
        ], 202);
    }
}
