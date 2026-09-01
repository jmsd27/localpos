<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SyncIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SyncIngestController extends Controller
{
    public function ingest(Request $request, SyncIngestionService $service): JsonResponse
    {
        if (config('sync.role') !== 'mirror') {
            abort(404);
        }

        // Es una API de máquina a máquina: siempre responde JSON, sin
        // importar el header Accept que mande el cliente.
        $validator = Validator::make($request->all(), [
            'branch_code' => ['required', 'string'],
            'entries' => ['present', 'array'],
            'entries.*.id' => ['required', 'integer'],
            'entries.*.model_type' => ['required', 'string'],
            'entries.*.model_id' => ['required', 'integer'],
            'entries.*.operation' => ['required', 'string', 'in:created,updated,deleted'],
            'entries.*.payload' => ['nullable', 'array'],
            'entries.*.occurred_at' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Payload inválido', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (empty($data['entries'])) {
            return response()->json(['accepted' => [], 'rejected' => []]);
        }

        $result = $service->applyBatch($data['branch_code'], $data['entries']);

        return response()->json($result);
    }
}
