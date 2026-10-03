<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Operations\Exceptions\OperationException;
use App\Services\Operations\TransactionalOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OperationApiController extends Controller
{
    public function __construct(
        protected TransactionalOperationService $operationService
    ) {}

    /**
     * Umumiy idempotent yozuvchi API amali
     */
    public function execute(Request $request): JsonResponse
    {
        $operationId = $request->header('X-Operation-Id')
            ?? $request->input('operation_id')
            ?? (string) Str::uuid();

        $operationType = $request->input('operation_type', 'GENERIC_OPERATION');
        $payload = $request->input('payload', $request->except(['operation_id', 'operation_type']));
        $deviceId = $request->header('X-Device-Id', $request->input('device_id'));
        $source = $request->header('X-Source', $request->input('source', 'web'));
        $expectedVersion = $request->input('expected_version');

        try {
            $result = $this->operationService->execute(
                operationId: $operationId,
                operationType: $operationType,
                payload: $payload,
                businessCallback: function ($context) use ($payload, $operationType) {
                    // Masalan sinov operatsiyasi yoki keyingi modullar amali
                    return [
                        'operation_type' => $operationType,
                        'received_payload' => $payload,
                        'message' => 'Operatsiya muvaffaqiyatli bajarildi.',
                        'processed_at' => now()->toIso8601String(),
                    ];
                },
                actorId: auth()->id(),
                deviceId: $deviceId,
                source: $source,
                expectedVersion: $expectedVersion ? (int) $expectedVersion : null
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200);
        } catch (OperationException $e) {
            return response()->json($e->toResponseArray(), $e->statusCode);
        }
    }
}
