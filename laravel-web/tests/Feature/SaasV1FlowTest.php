<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\ParameterSchemaVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaasV1FlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_parameter_schema_and_read_versions(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = $this->issueToken($admin);

        $csvContent = implode("\n", [
            'name,type,min,max,weight,is_core',
            'kip_sma,integer,0,1,1,true',
            'penghasilan_gabungan,float,0,999999999,1,true',
            'daya_listrik,integer,0,10000,1,true',
            'tanggungan_keluarga,integer,0,20,0.4,false',
        ]);

        $file = UploadedFile::fake()->createWithContent('schema-params.csv', $csvContent);

        $importResponse = $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Accept', 'application/json')
            ->post('/api/admin/parameters/import', [
                'file' => $file,
            ]);

        $importResponse
            ->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.schema_version', 1)
            ->assertJsonPath('data.parameter_count', 4)
            ->assertJsonPath('data.core_parameter_count', 3);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/parameters/schema-versions')
            ->assertStatus(200)
            ->assertJsonPath('data.0.version', 1)
            ->assertJsonPath('data.0.is_active', true);
    }

    public function test_student_submit_and_admin_verify_persists_training_data(): void
    {
        $student = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $studentToken = $this->issueToken($student);
        $adminToken = $this->issueToken($admin);

        Http::fake([
            'http://flask-api:5000/api/predict' => Http::response([
                'status' => 'success',
                'model_results' => [
                    'catboost' => ['label' => 'Layak', 'confidence' => 0.83],
                    'naive_bayes' => ['label' => 'Tidak Layak', 'confidence' => 0.72],
                    'disagreement_flag' => true,
                    'final_recommendation' => 'Layak',
                    'review_priority' => 'high',
                    'model_ready' => true,
                ],
            ], 200),
        ]);

        $submitResponse = $this
            ->withHeader('Authorization', "Bearer {$studentToken}")
            ->postJson('/api/student/applications', [
                'kip_sma' => 1,
                'penghasilan_gabungan' => 1200000,
                'daya_listrik' => 900,
            ]);

        $submitResponse
            ->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.final_recommendation', 'Layak')
            ->assertJsonPath('data.rule_recommendation', 'Layak')
            ->assertJsonPath('data.disagreement_flag', true)
            ->assertJsonPath('data.review_priority', 'high');

        $applicationId = $submitResponse->json('data.id');

        $verifyResponse = $this
            ->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson("/api/admin/applications/{$applicationId}/verify", [
                'note' => 'Data valid dan memenuhi kriteria',
            ]);

        $verifyResponse
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'Verified');

        $this->assertDatabaseHas('student_applications', [
            'id' => $applicationId,
            'status' => 'Verified',
            'admin_decision' => 'Verified',
            'rule_recommendation' => 'Layak',
        ]);

        $this->assertDatabaseHas('spk_training_data', [
            'source_application_id' => $applicationId,
            'label' => 'Layak',
            'schema_version' => 1,
        ]);

        $this->assertSame(
            2,
            DB::table('application_status_logs')->where('application_id', $applicationId)->count()
        );
    }

    public function test_student_can_upload_single_pdf_document_per_application(): void
    {
        Storage::fake('public');

        $student = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $studentToken = $this->issueToken($student);

        Http::fake([
            'http://flask-api:5000/api/predict' => Http::response([
                'status' => 'success',
                'model_results' => [
                    'catboost' => ['label' => 'Layak', 'confidence' => 0.81],
                    'naive_bayes' => ['label' => 'Layak', 'confidence' => 0.73],
                    'disagreement_flag' => false,
                    'final_recommendation' => 'Layak',
                    'review_priority' => 'normal',
                    'model_ready' => true,
                ],
            ], 200),
        ]);

        $submitResponse = $this
            ->withHeader('Authorization', "Bearer {$studentToken}")
            ->postJson('/api/student/applications', [
                'kip_sma' => 1,
                'penghasilan_gabungan' => 1000000,
                'daya_listrik' => 900,
            ]);

        $submitResponse->assertStatus(201);
        $applicationId = $submitResponse->json('data.id');

        $pdf = UploadedFile::fake()->create('dokumen-pendukung.pdf', 500, 'application/pdf');

        $uploadResponse = $this
            ->withHeader('Authorization', "Bearer {$studentToken}")
            ->withHeader('Accept', 'application/json')
            ->post("/api/student/applications/{$applicationId}/document", [
                'supporting_document_pdf' => $pdf,
            ]);

        $uploadResponse
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $updated = DB::table('student_applications')->where('id', $applicationId)->first();
        $this->assertNotNull($updated->supporting_document_path);
        $this->assertNotNull($updated->supporting_document_url);
        $this->assertNotNull($updated->document_submission_link);
    }

    public function test_mahasiswa_cannot_access_admin_routes(): void
    {
        $student = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $token = $this->issueToken($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/models/retrain')
            ->assertStatus(403)
            ->assertJsonPath('status', 'error');
    }

    public function test_admin_retrain_uses_active_schema_version_payload(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = $this->issueToken($admin);

        ParameterSchemaVersion::query()->create([
            'version' => 2,
            'source_file_name' => 'schema-v2.csv',
            'parameter_definitions' => [
                ['name' => 'kip_sma', 'type' => 'integer', 'is_core' => true],
                ['name' => 'penghasilan_gabungan', 'type' => 'float', 'is_core' => true],
                ['name' => 'daya_listrik', 'type' => 'integer', 'is_core' => true],
            ],
            'is_active' => true,
            'imported_by' => $admin->id,
        ]);

        Http::fake([
            'http://flask-api:5000/api/retrain' => Http::response([
                'status' => 'success',
                'message' => 'ok',
                'training_summary' => [
                    'rows_used' => 10,
                ],
            ], 200),
        ]);

        $response = $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/models/retrain');

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('schema_version', 2);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://flask-api:5000/api/retrain'
                && ($request['schema_version'] ?? null) === 2;
        });
    }

    private function issueToken(User $user): string
    {
        $rawToken = Str::random(80);

        ApiToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
        ]);

        return $rawToken;
    }
}
