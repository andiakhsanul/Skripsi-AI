<?php

namespace App\Http\Controllers;

use App\Services\MlGatewayService;
use App\Services\ParameterSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminModelController extends Controller
{
    public function __construct(
        private readonly MlGatewayService $mlGatewayService,
        private readonly ParameterSchemaService $parameterSchemaService,
    ) {}

    public function retrain(Request $request): JsonResponse
    {
        $schema = $this->parameterSchemaService->getActiveSchema();
        $schemaVersion = $schema?->version ?? 1;

        try {
            $mlResponse = $this->mlGatewayService->retrain([
                'triggered_by' => $request->user()->email,
                'schema_version' => $schemaVersion,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menjalankan retrain ke service ML',
                'detail' => $th->getMessage(),
            ], 502);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Retrain model berhasil dipicu',
            'schema_version' => $schemaVersion,
            'ml_response' => $mlResponse,
        ]);
    }
}
