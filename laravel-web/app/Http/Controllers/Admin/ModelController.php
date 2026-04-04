<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminModelRetrainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModelController extends Controller
{
    public function __construct(
        private readonly AdminModelRetrainService $adminModelRetrainService,
    ) {}

    public function retrain(Request $request): JsonResponse
    {
        try {
            $result = $this->adminModelRetrainService->triggerRetrain($request->user());
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
            'schema_version' => $result['schema_version'],
            'training_rows' => $result['training_rows'],
            'ml_response' => $result['ml_response'],
        ]);
    }
}
