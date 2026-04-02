<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Services\MlGatewayService;
use Illuminate\Http\JsonResponse;

class SpkController extends Controller
{
    public function __construct(
        private readonly MlGatewayService $mlGatewayService,
    ) {}

    public function runPrediction(): JsonResponse
    {
        try {
            $mlResponse = $this->mlGatewayService->predict([
                'kip' => 1,
                'pkh' => 1,
                'kks' => 1,
                'dtks' => 1,
                'sktm' => 0,
                'penghasilan_gabungan' => 1,
                'penghasilan_ayah' => 1,
                'penghasilan_ibu' => 1,
                'jumlah_tanggungan' => 2,
                'anak_ke' => 2,
                'status_orangtua' => 3,
                'status_rumah' => 2,
                'daya_listrik' => 2,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Prediksi berhasil dijalankan',
                'ml_response' => $mlResponse,
            ]);
        } catch (\Throwable $throwable) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat terhubung ke service ML (Flask)',
                'detail' => $throwable->getMessage(),
            ], 500);
        }
    }

    public function triggerRetrain(): JsonResponse
    {
        try {
            $mlResponse = $this->mlGatewayService->retrain([
                'triggered_by' => 'laravel-admin',
                'note' => 'Retrain dipicu setelah data dibersihkan oleh admin',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Retrain model berhasil dipicu dari Laravel',
                'ml_response' => $mlResponse,
            ]);
        } catch (\Throwable $throwable) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menghubungi endpoint retrain Flask',
                'detail' => $throwable->getMessage(),
            ], 500);
        }
    }
}
