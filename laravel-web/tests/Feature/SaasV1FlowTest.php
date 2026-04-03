<?php

namespace Tests\Feature;

use App\Models\ApplicationModelSnapshot;
use App\Models\ParameterSchemaVersion;
use App\Models\StudentApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SaasV1FlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_parameter_schema_and_read_versions(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $csvContent = implode("\n", [
            'name,type,min,max,weight,is_core',
            'kip,integer,0,1,1,true',
            'pkh,integer,0,1,1,true',
            'kks,integer,0,1,1,true',
            'dtks,integer,0,1,1,true',
            'sktm,integer,0,1,1,true',
            'penghasilan_gabungan,integer,1,3,1,true',
            'penghasilan_ayah,integer,1,3,1,true',
            'penghasilan_ibu,integer,1,3,1,true',
            'jumlah_tanggungan,integer,1,3,1,true',
            'anak_ke,integer,1,3,1,true',
            'status_orangtua,integer,1,3,1,true',
            'status_rumah,integer,1,3,1,true',
            'daya_listrik,integer,1,3,1,true',
        ]);

        $file = UploadedFile::fake()->createWithContent('schema-params.csv', $csvContent);

        $importResponse = $this
            ->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post('/api/admin/parameters/import', [
                'file' => $file,
            ]);

        $importResponse
            ->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.schema_version', 1)
            ->assertJsonPath('data.parameter_count', 13)
            ->assertJsonPath('data.core_parameter_count', 13);

        $this->actingAs($admin)
            ->getJson('/api/admin/parameters/schema-versions')
            ->assertStatus(200)
            ->assertJsonPath('data.0.version', 1)
            ->assertJsonPath('data.0.is_active', true);
    }

    public function test_student_submit_and_admin_verify_persists_training_data(): void
    {
        Storage::fake('public');

        $student = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Http::fake([
            'http://flask-api:5000/api/predict' => Http::response([
                'status' => 'success',
                'model_version_id' => 7,
                'model_version_name' => 'ready-schema-v1-20260402T100000000000Z',
                'model_results' => [
                    'catboost' => ['label' => 'Layak', 'confidence' => 0.83],
                    'naive_bayes' => ['label' => 'Indikasi', 'confidence' => 0.72],
                    'disagreement_flag' => true,
                    'final_recommendation' => 'Layak',
                    'review_priority' => 'high',
                    'model_ready' => true,
                ],
            ], 200),
        ]);

        $submitResponse = $this
            ->actingAs($student)
            ->withHeader('Accept', 'application/json')
            ->post('/api/student/applications', [
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
                'submitted_pdf' => UploadedFile::fake()->create('formulir-kipk.pdf', 500, 'application/pdf'),
            ]);

        $submitResponse
            ->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'Submitted');

        $applicationId = $submitResponse->json('data.id');

        $verifyResponse = $this
            ->actingAs($admin)
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
        ]);

        $this->assertDatabaseHas('application_model_snapshots', [
            'application_id' => $applicationId,
            'model_version_id' => 7,
            'final_recommendation' => 'Layak',
            'review_priority' => 'high',
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

    public function test_student_submission_stores_the_required_pdf(): void
    {
        Storage::fake('public');

        $student = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

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
            ->actingAs($student)
            ->withHeader('Accept', 'application/json')
            ->post('/api/student/applications', [
                'kip' => 1,
                'pkh' => 1,
                'kks' => 1,
                'dtks' => 0,
                'sktm' => 1,
                'penghasilan_gabungan' => 1,
                'penghasilan_ayah' => 1,
                'penghasilan_ibu' => 1,
                'jumlah_tanggungan' => 1,
                'anak_ke' => 2,
                'status_orangtua' => 2,
                'status_rumah' => 2,
                'daya_listrik' => 2,
                'submitted_pdf' => UploadedFile::fake()->create('dokumen-pengajuan.pdf', 500, 'application/pdf'),
            ]);

        $submitResponse
            ->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $applicationId = $submitResponse->json('data.id');
        $updated = DB::table('student_applications')->where('id', $applicationId)->first();

        $this->assertNotNull($updated->submitted_pdf_path);
        $this->assertSame('dokumen-pengajuan.pdf', $updated->submitted_pdf_original_name);
        $this->assertNotNull($updated->submitted_pdf_uploaded_at);
    }

    public function test_mahasiswa_cannot_access_admin_routes(): void
    {
        $student = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $this->actingAs($student)
            ->postJson('/api/admin/models/retrain')
            ->assertStatus(403)
            ->assertJsonPath('status', 'error');
    }

    public function test_web_logout_ends_the_current_session(): void
    {
        $student = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $response = $this
            ->actingAs($student)
            ->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_can_login_via_web_form_with_explicit_role(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-login@example.com',
            'password' => 'admin12345',
            'role' => 'admin',
        ]);

        $response = $this->from(route('login'))->post(route('login.post'), [
            'email' => 'ADMIN-LOGIN@EXAMPLE.COM',
            'password' => 'admin12345',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_fails_when_role_does_not_match_user_role(): void
    {
        User::factory()->create([
            'email' => 'admin-mismatch@example.com',
            'password' => 'admin12345',
            'role' => 'admin',
        ]);

        $response = $this->from(route('login'))->post(route('login.post'), [
            'email' => 'admin-mismatch@example.com',
            'password' => 'admin12345',
            'role' => 'mahasiswa',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guest_can_register_student_account_via_web_form(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Mahasiswa Baru',
            'email' => 'maba@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('student.dashboard'));

        $this->assertDatabaseHas('users', [
            'name' => 'Mahasiswa Baru',
            'email' => 'maba@example.com',
            'role' => 'mahasiswa',
        ]);

        $this->assertAuthenticated();
    }

    public function test_mahasiswa_cannot_access_legacy_ml_routes(): void
    {
        $student = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $this->actingAs($student)
            ->postJson('/api/spk/retrain-model')
            ->assertStatus(403)
            ->assertJsonPath('status', 'error');
    }

    public function test_admin_retrain_uses_active_schema_version_payload(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        ParameterSchemaVersion::query()->create([
            'version' => 2,
            'source_file_name' => 'schema-v2.csv',
            'parameter_definitions' => [
                ['name' => 'kip', 'type' => 'integer', 'is_core' => true],
                ['name' => 'pkh', 'type' => 'integer', 'is_core' => true],
                ['name' => 'kks', 'type' => 'integer', 'is_core' => true],
                ['name' => 'dtks', 'type' => 'integer', 'is_core' => true],
                ['name' => 'sktm', 'type' => 'integer', 'is_core' => true],
                ['name' => 'penghasilan_gabungan', 'type' => 'integer', 'is_core' => true],
                ['name' => 'penghasilan_ayah', 'type' => 'integer', 'is_core' => true],
                ['name' => 'penghasilan_ibu', 'type' => 'integer', 'is_core' => true],
                ['name' => 'jumlah_tanggungan', 'type' => 'integer', 'is_core' => true],
                ['name' => 'anak_ke', 'type' => 'integer', 'is_core' => true],
                ['name' => 'status_orangtua', 'type' => 'integer', 'is_core' => true],
                ['name' => 'status_rumah', 'type' => 'integer', 'is_core' => true],
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
            ->actingAs($admin)
            ->postJson('/api/admin/models/retrain');

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('schema_version', 2);

        Http::assertSent(function ($request) use ($admin): bool {
            return $request->url() === 'http://flask-api:5000/api/retrain'
                && ($request['schema_version'] ?? null) === 2
                && ($request['triggered_by_user_id'] ?? null) === $admin->id
                && ($request['triggered_by_email'] ?? null) === $admin->email;
        });
    }

    public function test_student_dashboard_page_renders_history_and_summary_cards(): void
    {
        Storage::fake('public');

        $student = User::factory()->create([
            'name' => 'Bunga Maharani',
            'email' => 'bunga@example.com',
            'role' => 'mahasiswa',
        ]);

        StudentApplication::query()->create([
            'student_user_id' => $student->id,
            'schema_version' => 1,
            'submission_source' => 'online_student',
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 2,
            'jumlah_tanggungan' => 2,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'submitted_pdf_path' => 'applications/bunga-maharani.pdf',
            'submitted_pdf_original_name' => 'bunga-maharani.pdf',
            'submitted_pdf_uploaded_at' => now(),
            'status' => 'Submitted',
        ]);

        $response = $this
            ->actingAs($student)
            ->get(route('student.dashboard'));

        $response
            ->assertOk()
            ->assertSee('History of Applications')
            ->assertSee('Bunga Maharani')
            ->assertSee('Form Pengajuan Menyusul');
    }

    public function test_admin_dashboard_page_renders_summary_and_application_rows(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin UNAIR',
            'role' => 'admin',
        ]);

        $student = User::factory()->create([
            'name' => 'Bunga Maharani',
            'email' => 'bunga@example.com',
            'role' => 'mahasiswa',
        ]);

        $application = StudentApplication::query()->create([
            'student_user_id' => $student->id,
            'schema_version' => 1,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 2,
            'penghasilan_ibu' => 2,
            'jumlah_tanggungan' => 1,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'submitted_pdf_path' => 'applications/bunga-maharani.pdf',
            'submitted_pdf_original_name' => 'bunga-maharani.pdf',
            'submitted_pdf_uploaded_at' => now(),
            'status' => 'Submitted',
        ]);

        ApplicationModelSnapshot::query()->create([
            'application_id' => $application->id,
            'schema_version' => 1,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 2,
            'penghasilan_ibu' => 2,
            'jumlah_tanggungan' => 1,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'model_ready' => true,
            'catboost_label' => 'Layak',
            'catboost_confidence' => 0.8700,
            'naive_bayes_label' => 'Indikasi',
            'naive_bayes_confidence' => 0.7100,
            'disagreement_flag' => true,
            'final_recommendation' => 'Layak',
            'review_priority' => 'high',
            'rule_score' => 0.6500,
            'rule_recommendation' => 'Layak',
            'snapshotted_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSee('Scholarship Dashboard')
            ->assertSee('Bunga Maharani')
            ->assertSee('Retrain Model')
            ->assertSee('CatBoost dan Naive Bayes memberi hasil berbeda.');
    }
}
