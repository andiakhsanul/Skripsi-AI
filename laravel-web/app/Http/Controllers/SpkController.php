<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class SpkController extends Controller
{
    public function runPrediction(): JsonResponse
    {
        try {
            // URL service Flask diambil dari environment agar fleksibel antar lingkungan.
            $flaskApiUrl = env('FLASK_API_URL', 'http://flask-api:5000/api/predict');
            $internalToken = env('FLASK_INTERNAL_TOKEN', 'spk_internal_dev_token');

            // Payload dummy untuk simulasi request dari Laravel ke Flask API.
            $payload = [
                'kip_sma' => 1,
                'penghasilan_gabungan' => 1500000,
                'daya_listrik' => 900,
            ];

            $response = Http::timeout(5)
                ->retry(1, 200)
                ->withHeaders([
                    'X-Internal-Token' => $internalToken,
                ])
                ->post($flaskApiUrl, $payload);

            if ($response->successful()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Prediksi berhasil dijalankan',
                    'ml_response' => $response->json(),
                ], 200);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Service ML merespons dengan status gagal',
                'http_status' => $response->status(),
                'ml_response' => $response->json(),
            ], 502);
        } catch (\Throwable $th) {
            // Menangkap timeout, kegagalan koneksi, atau exception lain dari HTTP client.
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat terhubung ke service ML (Flask)',
                'detail' => $th->getMessage(),
            ], 500);
        }
    }

    public function triggerRetrain(): JsonResponse
    {
        try {
            // Endpoint retrain dipisahkan agar bisa dipanggil setelah admin memvalidasi data.
            $retrainUrl = env('FLASK_RETRAIN_URL', 'http://flask-api:5000/api/retrain');
            $internalToken = env('FLASK_INTERNAL_TOKEN', 'spk_internal_dev_token');

            $payload = [
                'triggered_by' => 'laravel-admin',
                'note' => 'Retrain dipicu setelah data dibersihkan oleh admin',
            ];

            $response = Http::timeout(30)
                ->retry(1, 300)
                ->withHeaders([
                    'X-Internal-Token' => $internalToken,
                ])
                ->post($retrainUrl, $payload);

            if ($response->successful()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Retrain model berhasil dipicu dari Laravel',
                    'ml_response' => $response->json(),
                ], 200);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Service ML gagal menjalankan retrain',
                'http_status' => $response->status(),
                'ml_response' => $response->json(),
            ], 502);
        } catch (\Throwable $th) {
            // Menangkap error timeout, koneksi putus, atau service Flask belum siap.
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menghubungi endpoint retrain Flask',
                'detail' => $th->getMessage(),
            ], 500);
        }
    }
}
