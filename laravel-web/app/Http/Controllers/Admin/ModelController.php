<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MlGatewayService;
use App\Services\ParameterSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModelController extends Controller
{
    public function __construct(
        private readonly MlGatewayService $mlGatewayService,
        private readonly ParameterSchemaService $parameterSchemaService,
    ) {}

    public function retrain(Request $request): JsonResponse
    {
        $schemaVersion = $this->parameterSchemaService->getActiveSchema()?->version ?? 1;

        try {
            $mlResponse = $this->mlGatewayService->retrain([
                'triggered_by_user_id' => $request->user()->id,
                'triggered_by_email' => $request->user()->email,
                'schema_version' => $schemaVersion,
            ]);
        } catch (\Throwable $throwable) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menjalankan retrain ke service ML',
                'detail' => $throwable->getMessage(),
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
