<?php

namespace Tests\Feature;

use App\Models\ApplicationFeatureEncoding;
use App\Models\ApplicationModelSnapshot;
use App\Models\ModelVersion;
use App\Models\ParameterSchemaVersion;
use App\Models\SpkTrainingData;
use App\Models\StudentApplication;
use App\Models\User;
use App\Services\OfflineImport\CsvStudentApplicationImporter;
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
                'penghasilan_ayah_rupiah' => 700000,
                'penghasilan_ibu_rupiah' => 200000,
                'jumlah_tanggungan_raw' => 4,
                'anak_ke_raw' => 3,
                'status_orangtua_text' => 'Lengkap',
                'status_rumah_text' => 'Sewa',
                'daya_listrik_text' => '900 VA',
                'submitted_pdf' => UploadedFile::fake()->create('formulir-kipk.pdf', 500, 'application/pdf'),
            ]);

        $submitResponse
            ->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'Submitted');

        $applicationId = $submitResponse->json('data.id');
        $encoding = ApplicationFeatureEncoding::query()->where('application_id', $applicationId)->firstOrFail();

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
            'penghasilan_ayah_rupiah' => 700000,
            'penghasilan_gabungan_rupiah' => 900000,
        ]);

        $this->assertDatabaseHas('application_feature_encodings', [
            'id' => $encoding->id,
            'application_id' => $applicationId,
            'penghasilan_gabungan' => 1,
            'jumlah_tanggungan' => 2,
            'anak_ke' => 2,
            'status_rumah' => 2,
        ]);

        $this->assertDatabaseHas('application_model_snapshots', [
            'application_id' => $applicationId,
            'encoding_id' => $encoding->id,
            'model_version_id' => 7,
            'final_recommendation' => 'Layak',
            'review_priority' => 'high',
        ]);

        $this->assertDatabaseHas('spk_training_data', [
            'source_application_id' => $applicationId,
            'source_encoding_id' => $encoding->id,
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
                'penghasilan_ayah_rupiah' => 500000,
                'penghasilan_ibu_rupiah' => 0,
                'jumlah_tanggungan_raw' => 6,
                'anak_ke_raw' => 5,
                'status_orangtua_text' => 'Yatim',
                'status_rumah_text' => 'Menumpang',
                'daya_listrik_text' => '450 VA',
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

        SpkTrainingData::query()->create([
            'schema_version' => 2,
            'encoding_version' => 1,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 2,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'label' => 'Layak',
            'label_class' => 0,
            'decision_status' => 'Verified',
            'finalized_by_user_id' => $admin->id,
            'finalized_at' => now(),
            'is_active' => true,
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

    public function test_admin_can_sync_finalized_training_for_raw_applications(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $application = StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Citra Ayuningtyas',
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 500000,
            'penghasilan_ibu_rupiah' => 100000,
            'penghasilan_gabungan_rupiah' => 600000,
            'jumlah_tanggungan_raw' => 6,
            'anak_ke_raw' => 5,
            'status_orangtua_text' => 'ayah=hidup; ibu=hidup',
            'status_rumah_text' => 'menumpang',
            'daya_listrik_text' => '450 VA',
            'status' => 'Verified',
            'admin_decision' => 'Verified',
            'admin_decided_by' => $admin->id,
            'admin_decided_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson('/api/admin/training/sync-finalized');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.processed', 1)
            ->assertJsonPath('data.synced', 1);

        $encoding = ApplicationFeatureEncoding::query()->where('application_id', $application->id)->firstOrFail();

        $this->assertDatabaseHas('spk_training_data', [
            'source_application_id' => $application->id,
            'source_encoding_id' => $encoding->id,
            'label' => 'Layak',
            'label_class' => 0,
        ]);
    }

    public function test_admin_can_sync_finalized_training_while_skipping_invalid_raw_rows(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Applicant Valid',
            'kip' => 1,
            'pkh' => 0,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 0,
            'penghasilan_ayah_rupiah' => 800000,
            'penghasilan_ibu_rupiah' => 200000,
            'penghasilan_gabungan_rupiah' => 1000000,
            'jumlah_tanggungan_raw' => 4,
            'anak_ke_raw' => 2,
            'status_orangtua_text' => 'ayah=hidup; ibu=hidup',
            'status_rumah_text' => 'Sewa',
            'daya_listrik_text' => '900',
            'status' => 'Verified',
            'admin_decision' => 'Verified',
            'admin_decided_by' => $admin->id,
            'admin_decided_at' => now(),
        ]);

        StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Applicant Invalid',
            'kip' => 1,
            'pkh' => 0,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 0,
            'penghasilan_ayah_rupiah' => 800000,
            'penghasilan_ibu_rupiah' => null,
            'penghasilan_gabungan_rupiah' => 800000,
            'jumlah_tanggungan_raw' => 4,
            'anak_ke_raw' => 2,
            'status_orangtua_text' => 'ayah=hidup; ibu=hidup',
            'status_rumah_text' => 'Menumpang',
            'daya_listrik_text' => '900',
            'status' => 'Rejected',
            'admin_decision' => 'Rejected',
            'admin_decided_by' => $admin->id,
            'admin_decided_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson('/api/admin/training/sync-finalized');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.processed', 2)
            ->assertJsonPath('data.synced', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonCount(1, 'data.skipped_applications');

        $this->assertDatabaseCount('spk_training_data', 1);
        $this->assertDatabaseHas('spk_training_data', [
            'label' => 'Layak',
        ]);
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
            'applicant_name' => $student->name,
            'applicant_email' => $student->email,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 1000000,
            'penghasilan_ibu_rupiah' => 500000,
            'penghasilan_gabungan_rupiah' => 1500000,
            'jumlah_tanggungan_raw' => 4,
            'anak_ke_raw' => 3,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Sewa',
            'daya_listrik_text' => '900 VA',
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
            ->assertSee('Riwayat Pengajuan')
            ->assertSee('Bunga Maharani')
            ->assertSee('Ajukan KIP-K');
    }

    public function test_student_dashboard_shows_final_decision_notification(): void
    {
        $student = User::factory()->create([
            'name' => 'Dina Larasati',
            'email' => 'dina@example.com',
            'role' => 'mahasiswa',
        ]);

        StudentApplication::query()->create([
            'student_user_id' => $student->id,
            'schema_version' => 1,
            'submission_source' => 'online_student',
            'applicant_name' => $student->name,
            'applicant_email' => $student->email,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 1,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 900000,
            'penghasilan_ibu_rupiah' => 200000,
            'penghasilan_gabungan_rupiah' => 1100000,
            'jumlah_tanggungan_raw' => 4,
            'anak_ke_raw' => 2,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Menumpang',
            'daya_listrik_text' => '450 VA',
            'status' => 'Verified',
            'admin_decision' => 'Verified',
            'admin_decision_note' => 'Data lengkap dan sesuai bukti.',
            'admin_decided_at' => now(),
        ]);

        $response = $this
            ->actingAs($student)
            ->get(route('student.dashboard'));

        $response
            ->assertOk()
            ->assertSee('Notifikasi Keputusan')
            ->assertSee('Lihat Keputusan')
            ->assertSee('Data lengkap dan sesuai bukti.');
    }

    public function test_student_can_view_web_application_form(): void
    {
        $student = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $response = $this
            ->actingAs($student)
            ->get(route('student.applications.create'));

        $response
            ->assertOk()
            ->assertSee('Pengajuan KIP-K')
            ->assertSee('Kepemilikan Dokumen dan Bantuan Sosial')
            ->assertSee('Kirim Pengajuan');
    }

    public function test_student_can_submit_application_from_web_form(): void
    {
        Storage::fake('public');

        $student = User::factory()->create([
            'name' => 'Nadia Maharani',
            'email' => 'nadia-web@example.com',
            'role' => 'mahasiswa',
        ]);

        Http::fake([
            'http://flask-api:5000/api/predict' => Http::response([
                'status' => 'success',
                'model_version_id' => 14,
                'model_version_name' => 'ready-schema-v1-web-demo',
                'model_results' => [
                    'catboost' => ['label' => 'Layak', 'confidence' => 0.82],
                    'naive_bayes' => ['label' => 'Layak', 'confidence' => 0.75],
                    'disagreement_flag' => false,
                    'final_recommendation' => 'Layak',
                    'review_priority' => 'normal',
                    'model_ready' => true,
                ],
            ], 200),
        ]);

        $response = $this
            ->actingAs($student)
            ->post(route('student.applications.store'), [
                'kip' => 1,
                'pkh' => 0,
                'kks' => 1,
                'dtks' => 0,
                'sktm' => 1,
                'penghasilan_ayah_rupiah' => 800000,
                'penghasilan_ibu_rupiah' => 200000,
                'jumlah_tanggungan_raw' => 5,
                'anak_ke_raw' => 3,
                'status_orangtua_text' => 'Lengkap',
                'status_rumah_text' => 'Menumpang',
                'daya_listrik_text' => '450 VA',
                'submitted_pdf' => UploadedFile::fake()->create('pengajuan-web.pdf', 500, 'application/pdf'),
            ]);

        $application = StudentApplication::query()->where('student_user_id', $student->id)->latest('id')->firstOrFail();

        $response
            ->assertRedirect(route('student.applications.show', $application->id));

        $response->assertSessionHas('student_notice.title', 'Pengajuan berhasil dikirim');

        $this->assertDatabaseHas('student_applications', [
            'id' => $application->id,
            'student_user_id' => $student->id,
            'status' => 'Submitted',
            'penghasilan_gabungan_rupiah' => 1000000,
        ]);

        $this->assertDatabaseHas('application_model_snapshots', [
            'application_id' => $application->id,
            'model_version_id' => 14,
            'final_recommendation' => 'Layak',
        ]);
    }

    public function test_student_can_revise_application_before_admin_final_decision(): void
    {
        Storage::fake('public');

        $student = User::factory()->create([
            'name' => 'Rani Kusumawardani',
            'email' => 'rani@example.com',
            'role' => 'mahasiswa',
        ]);

        Storage::disk('public')->put('student-application-pdfs/old-rani.pdf', 'dokumen-lama');

        $application = StudentApplication::query()->create([
            'student_user_id' => $student->id,
            'schema_version' => 1,
            'submission_source' => 'online_student',
            'applicant_name' => $student->name,
            'applicant_email' => $student->email,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 900000,
            'penghasilan_ibu_rupiah' => 100000,
            'penghasilan_gabungan_rupiah' => 1000000,
            'jumlah_tanggungan_raw' => 4,
            'anak_ke_raw' => 2,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Menumpang',
            'daya_listrik_text' => '450 VA',
            'submitted_pdf_path' => 'student-application-pdfs/old-rani.pdf',
            'submitted_pdf_original_name' => 'old-rani.pdf',
            'submitted_pdf_uploaded_at' => now()->subDay(),
            'status' => 'Submitted',
        ]);

        Http::fake([
            'http://flask-api:5000/api/predict' => Http::response([
                'status' => 'success',
                'model_version_id' => 21,
                'model_version_name' => 'ready-schema-v1-rani-revisi',
                'model_results' => [
                    'catboost' => ['label' => 'Indikasi', 'confidence' => 0.77],
                    'naive_bayes' => ['label' => 'Indikasi', 'confidence' => 0.70],
                    'disagreement_flag' => false,
                    'final_recommendation' => 'Indikasi',
                    'review_priority' => 'high',
                    'model_ready' => true,
                ],
            ], 200),
        ]);

        $this
            ->actingAs($student)
            ->get(route('student.applications.edit', $application->id))
            ->assertOk()
            ->assertSee('Revisi Pengajuan KIP-K')
            ->assertSee('Rani Kusumawardani');

        $response = $this
            ->actingAs($student)
            ->post(route('student.applications.update', $application->id), [
                '_method' => 'PUT',
                'kip' => 1,
                'pkh' => 1,
                'kks' => 1,
                'dtks' => 1,
                'sktm' => 1,
                'penghasilan_ayah_rupiah' => 750000,
                'penghasilan_ibu_rupiah' => 250000,
                'jumlah_tanggungan_raw' => 5,
                'anak_ke_raw' => 3,
                'status_orangtua_text' => 'Lengkap',
                'status_rumah_text' => 'Sewa / Kontrak',
                'daya_listrik_text' => '900 VA',
                'submitted_pdf' => UploadedFile::fake()->create('revisi-rani.pdf', 400, 'application/pdf'),
            ]);

        $response->assertStatus(302);
        $this->assertSame(route('student.applications.show', $application->id), $response->headers->get('Location'));
        $response->assertSessionHas('student_notice.title', 'Pengajuan berhasil direvisi');

        $application->refresh();

        $this->assertSame(1000000, $application->penghasilan_gabungan_rupiah);
        $this->assertSame('Sewa / Kontrak', $application->status_rumah_text);
        $this->assertSame('revisi-rani.pdf', $application->submitted_pdf_original_name);
        $this->assertNotSame('student-application-pdfs/old-rani.pdf', $application->submitted_pdf_path);

        Storage::disk('public')->assertMissing('student-application-pdfs/old-rani.pdf');
        Storage::disk('public')->assertExists($application->submitted_pdf_path);

        $this->assertDatabaseHas('application_model_snapshots', [
            'application_id' => $application->id,
            'model_version_id' => 21,
            'final_recommendation' => 'Indikasi',
        ]);

        $this->assertDatabaseHas('application_status_logs', [
            'application_id' => $application->id,
            'action' => 'revised',
        ]);
    }

    public function test_student_cannot_revise_application_after_admin_final_decision(): void
    {
        $student = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $application = StudentApplication::query()->create([
            'student_user_id' => $student->id,
            'schema_version' => 1,
            'submission_source' => 'online_student',
            'applicant_name' => 'Alya Putri',
            'applicant_email' => 'alya-putri@example.com',
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 1000000,
            'penghasilan_ibu_rupiah' => 0,
            'penghasilan_gabungan_rupiah' => 1000000,
            'jumlah_tanggungan_raw' => 3,
            'anak_ke_raw' => 1,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Milik sendiri',
            'daya_listrik_text' => '1300 VA',
            'status' => 'Verified',
            'admin_decision' => 'Verified',
            'admin_decided_at' => now(),
        ]);

        $this
            ->actingAs($student)
            ->get(route('student.applications.edit', $application->id))
            ->assertForbidden();
    }

    public function test_student_can_view_own_application_detail_but_not_other_students(): void
    {
        $student = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $otherStudent = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $ownedApplication = StudentApplication::query()->create([
            'student_user_id' => $student->id,
            'schema_version' => 1,
            'submission_source' => 'online_student',
            'applicant_name' => 'Alya Prameswari',
            'applicant_email' => 'alya@example.com',
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 700000,
            'penghasilan_ibu_rupiah' => 100000,
            'penghasilan_gabungan_rupiah' => 800000,
            'jumlah_tanggungan_raw' => 6,
            'anak_ke_raw' => 4,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Sewa / Kontrak',
            'daya_listrik_text' => '450 VA',
            'status' => 'Submitted',
        ]);

        StudentApplication::query()->create([
            'student_user_id' => $otherStudent->id,
            'schema_version' => 1,
            'submission_source' => 'online_student',
            'applicant_name' => 'Rahasia Orang Lain',
            'applicant_email' => 'rahasia@example.com',
            'kip' => 1,
            'pkh' => 1,
            'kks' => 1,
            'dtks' => 1,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 500000,
            'penghasilan_ibu_rupiah' => 0,
            'penghasilan_gabungan_rupiah' => 500000,
            'jumlah_tanggungan_raw' => 3,
            'anak_ke_raw' => 1,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Milik sendiri',
            'daya_listrik_text' => '1300 VA',
            'status' => 'Submitted',
        ]);

        $detailResponse = $this
            ->actingAs($student)
            ->get(route('student.applications.show', $ownedApplication->id));

        $detailResponse
            ->assertOk()
            ->assertSee('Detail Pengajuan')
            ->assertSee('Alya Prameswari')
            ->assertSee('Ringkasan data mentah');

        $this
            ->actingAs($student)
            ->get(route('student.applications.show', $ownedApplication->id + 1))
            ->assertNotFound();
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
            'submission_source' => 'online_student',
            'applicant_name' => $student->name,
            'applicant_email' => $student->email,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 1500000,
            'penghasilan_ibu_rupiah' => 500000,
            'penghasilan_gabungan_rupiah' => 2000000,
            'jumlah_tanggungan_raw' => 6,
            'anak_ke_raw' => 3,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Sewa',
            'daya_listrik_text' => '900 VA',
            'submitted_pdf_path' => 'applications/bunga-maharani.pdf',
            'submitted_pdf_original_name' => 'bunga-maharani.pdf',
            'submitted_pdf_uploaded_at' => now(),
            'status' => 'Submitted',
        ]);

        $encoding = ApplicationFeatureEncoding::query()->create([
            'application_id' => $application->id,
            'schema_version' => 1,
            'encoding_version' => 1,
            'is_current' => true,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_gabungan' => 2,
            'penghasilan_ayah' => 2,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 1,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'encoded_at' => now(),
        ]);

        ApplicationModelSnapshot::query()->create([
            'application_id' => $application->id,
            'encoding_id' => $encoding->id,
            'schema_version' => 1,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_gabungan' => 2,
            'penghasilan_ayah' => 2,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 1,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'model_ready' => true,
            'catboost_label' => 'Indikasi',
            'catboost_confidence' => 0.8700,
            'naive_bayes_label' => 'Layak',
            'naive_bayes_confidence' => 0.7100,
            'disagreement_flag' => true,
            'final_recommendation' => 'Indikasi',
            'review_priority' => 'high',
            'rule_score' => 0.6500,
            'rule_recommendation' => 'Indikasi',
            'snapshotted_at' => now(),
        ]);

        StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Lia Permata',
            'applicant_email' => 'lia@example.com',
            'kip' => 1,
            'pkh' => 1,
            'kks' => 1,
            'dtks' => 1,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 500000,
            'penghasilan_ibu_rupiah' => 0,
            'penghasilan_gabungan_rupiah' => 500000,
            'jumlah_tanggungan_raw' => 6,
            'anak_ke_raw' => 5,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Menumpang',
            'daya_listrik_text' => '450 VA',
            'status' => 'Submitted',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSee('Dasbor Beasiswa')
            ->assertSee('Bunga Maharani')
            ->assertDontSee('Lia Permata')
            ->assertSee('Latih Ulang Model')
            ->assertSee('Fokus Review Awal')
            ->assertSee('Disagreement model')
            ->assertSee('Indikasi Menunggu')
            ->assertSee('Fokus Indikasi Menunggu')
            ->assertSee('Hanya Disagreement')
            ->assertSee('Semua Pengajuan');
    }

    public function test_admin_dashboard_can_filter_by_indikasi_recommendation_and_disagreement(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $indikasiApp = StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Nadia Putri',
            'applicant_email' => 'nadia@example.com',
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 3000000,
            'penghasilan_ibu_rupiah' => 1500000,
            'penghasilan_gabungan_rupiah' => 4500000,
            'jumlah_tanggungan_raw' => 2,
            'anak_ke_raw' => 1,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Milik sendiri',
            'daya_listrik_text' => '1300 VA',
            'status' => 'Submitted',
        ]);

        $indikasiEncoding = ApplicationFeatureEncoding::query()->create([
            'application_id' => $indikasiApp->id,
            'schema_version' => 1,
            'encoding_version' => 1,
            'is_current' => true,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_gabungan' => 3,
            'penghasilan_ayah' => 2,
            'penghasilan_ibu' => 2,
            'jumlah_tanggungan' => 3,
            'anak_ke' => 3,
            'status_orangtua' => 3,
            'status_rumah' => 3,
            'daya_listrik' => 3,
            'encoded_at' => now(),
        ]);

        ApplicationModelSnapshot::query()->create([
            'application_id' => $indikasiApp->id,
            'encoding_id' => $indikasiEncoding->id,
            'schema_version' => 1,
            'model_ready' => true,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_gabungan' => 3,
            'penghasilan_ayah' => 2,
            'penghasilan_ibu' => 2,
            'jumlah_tanggungan' => 3,
            'anak_ke' => 3,
            'status_orangtua' => 3,
            'status_rumah' => 3,
            'daya_listrik' => 3,
            'catboost_label' => 'Indikasi',
            'catboost_confidence' => 0.88,
            'naive_bayes_label' => 'Layak',
            'naive_bayes_confidence' => 0.61,
            'disagreement_flag' => true,
            'final_recommendation' => 'Indikasi',
            'review_priority' => 'high',
            'rule_score' => 0.41,
            'rule_recommendation' => 'Indikasi',
            'snapshotted_at' => now(),
        ]);

        $layakApp = StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Rani Lestari',
            'applicant_email' => 'rani@example.com',
            'kip' => 1,
            'pkh' => 1,
            'kks' => 1,
            'dtks' => 1,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 500000,
            'penghasilan_ibu_rupiah' => 0,
            'penghasilan_gabungan_rupiah' => 500000,
            'jumlah_tanggungan_raw' => 6,
            'anak_ke_raw' => 5,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Menumpang',
            'daya_listrik_text' => '450 VA',
            'status' => 'Submitted',
        ]);

        $layakEncoding = ApplicationFeatureEncoding::query()->create([
            'application_id' => $layakApp->id,
            'schema_version' => 1,
            'encoding_version' => 1,
            'is_current' => true,
            'kip' => 1,
            'pkh' => 1,
            'kks' => 1,
            'dtks' => 1,
            'sktm' => 1,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 1,
            'anak_ke' => 1,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'encoded_at' => now(),
        ]);

        ApplicationModelSnapshot::query()->create([
            'application_id' => $layakApp->id,
            'encoding_id' => $layakEncoding->id,
            'schema_version' => 1,
            'model_ready' => true,
            'kip' => 1,
            'pkh' => 1,
            'kks' => 1,
            'dtks' => 1,
            'sktm' => 1,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 1,
            'anak_ke' => 1,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'catboost_label' => 'Layak',
            'catboost_confidence' => 0.93,
            'naive_bayes_label' => 'Layak',
            'naive_bayes_confidence' => 0.82,
            'disagreement_flag' => false,
            'final_recommendation' => 'Layak',
            'review_priority' => 'normal',
            'rule_score' => 0.83,
            'rule_recommendation' => 'Layak',
            'snapshotted_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.dashboard', [
                'recommendation' => 'Indikasi',
                'disagreement' => 'true',
            ]));

        $response
            ->assertOk()
            ->assertSee('Nadia Putri')
            ->assertDontSee('Rani Lestari');
    }

    public function test_admin_retrain_page_renders_with_training_summary(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin UNAIR',
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.models.retrain'));

        $response
            ->assertOk()
            ->assertSee('Retrain Model')
            ->assertSee('Sinkronkan Data Latih')
            ->assertSee('Mulai Latih Ulang');
    }

    public function test_admin_retrain_page_shows_evaluation_metrics_for_active_model(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        ModelVersion::query()->create([
            'version_name' => 'ready-schema-v1-metrics',
            'schema_version' => 1,
            'status' => 'ready',
            'is_current' => true,
            'training_table' => 'spk_training_data',
            'primary_model' => 'catboost',
            'secondary_model' => 'categorical_nb',
            'rows_used' => 97,
            'train_rows' => 78,
            'validation_rows' => 19,
            'validation_strategy' => 'holdout_19_rows_stratified',
            'catboost_validation_accuracy' => 0.7368,
            'naive_bayes_validation_accuracy' => 0.6316,
            'catboost_metrics' => [
                'evaluation_dataset' => 'validation',
                'threshold' => 0.0827,
                'accuracy' => 0.7368,
                'balanced_accuracy' => 0.8125,
                'precision_indikasi' => 0.5714,
                'recall_indikasi' => 0.8,
                'f1_indikasi' => 0.6667,
                'fbeta_indikasi' => 0.8,
                'confusion_matrix' => ['tn' => 10, 'fp' => 3, 'fn' => 1, 'tp' => 5],
            ],
            'naive_bayes_metrics' => [
                'evaluation_dataset' => 'validation',
                'threshold' => 0.1,
                'accuracy' => 0.6316,
                'balanced_accuracy' => 0.7083,
                'precision_indikasi' => 0.4444,
                'recall_indikasi' => 0.8,
                'f1_indikasi' => 0.5714,
                'fbeta_indikasi' => 0.6897,
                'confusion_matrix' => ['tn' => 8, 'fp' => 5, 'fn' => 1, 'tp' => 5],
            ],
            'trained_at' => now(),
            'activated_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.models.retrain'));

        $response
            ->assertOk()
            ->assertSee('Precision Indikasi')
            ->assertSee('Recall Indikasi')
            ->assertSee('F1 Indikasi')
            ->assertSee('Balanced Accuracy');
    }

    public function test_admin_can_sync_training_and_trigger_retrain_from_web_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-retrain@example.com',
        ]);

        ParameterSchemaVersion::query()->create([
            'version' => 3,
            'source_file_name' => 'schema-v3.csv',
            'parameter_definitions' => [
                ['name' => 'kip', 'type' => 'integer', 'is_core' => true],
            ],
            'is_active' => true,
            'imported_by' => $admin->id,
        ]);

        $application = StudentApplication::query()->create([
            'schema_version' => 3,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Nabila Kusuma',
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 700000,
            'penghasilan_ibu_rupiah' => 200000,
            'penghasilan_gabungan_rupiah' => 900000,
            'jumlah_tanggungan_raw' => 6,
            'anak_ke_raw' => 4,
            'status_orangtua_text' => 'ayah=hidup; ibu=hidup',
            'status_rumah_text' => 'menumpang',
            'daya_listrik_text' => '450 VA',
            'status' => 'Verified',
            'admin_decision' => 'Verified',
            'admin_decided_by' => $admin->id,
            'admin_decided_at' => now(),
        ]);

        Http::fake([
            'http://flask-api:5000/api/retrain' => Http::response([
                'status' => 'success',
                'message' => 'Retrain model berhasil dijalankan',
                'schema_version' => 3,
                'training_summary' => [
                    'rows_used' => 1,
                    'model_version_name' => 'ready-schema-v3-demo',
                ],
            ], 200),
        ]);

        $syncResponse = $this
            ->actingAs($admin)
            ->post(route('admin.models.retrain.sync-training'));

        $syncResponse->assertRedirect(route('admin.models.retrain'));

        $encoding = ApplicationFeatureEncoding::query()->where('application_id', $application->id)->firstOrFail();

        $this->assertDatabaseHas('spk_training_data', [
            'source_application_id' => $application->id,
            'source_encoding_id' => $encoding->id,
            'label_class' => 0,
        ]);

        $retrainResponse = $this
            ->actingAs($admin)
            ->post(route('admin.models.retrain.run'));

        $retrainResponse->assertRedirect(route('admin.models.retrain'));

        Http::assertSent(function ($request) use ($admin): bool {
            return $request->url() === 'http://flask-api:5000/api/retrain'
                && ($request['schema_version'] ?? null) === 3
                && ($request['triggered_by_user_id'] ?? null) === $admin->id
                && ($request['triggered_by_email'] ?? null) === $admin->email;
        });
    }

    public function test_admin_can_activate_previous_ready_model_from_web_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-activate@example.com',
        ]);

        $currentVersion = ModelVersion::query()->create([
            'version_name' => 'ready-schema-v1-current',
            'schema_version' => 1,
            'status' => 'ready',
            'is_current' => true,
            'triggered_by_user_id' => $admin->id,
            'triggered_by_email' => $admin->email,
            'catboost_artifact_path' => 'models/catboost_ready-schema-v1-current.joblib',
            'naive_bayes_artifact_path' => 'models/naive_bayes_ready-schema-v1-current.joblib',
            'trained_at' => now(),
            'activated_at' => now(),
        ]);

        $targetVersion = ModelVersion::query()->create([
            'version_name' => 'ready-schema-v1-older',
            'schema_version' => 1,
            'status' => 'ready',
            'is_current' => false,
            'triggered_by_user_id' => $admin->id,
            'triggered_by_email' => $admin->email,
            'catboost_artifact_path' => 'models/catboost_ready-schema-v1-older.joblib',
            'naive_bayes_artifact_path' => 'models/naive_bayes_ready-schema-v1-older.joblib',
            'trained_at' => now()->subHour(),
        ]);

        Http::fake([
            'http://flask-api:5000/api/models/activate' => Http::response([
                'status' => 'success',
                'message' => 'Versi model aktif berhasil diperbarui',
                'model_version' => [
                    'id' => $targetVersion->id,
                    'version_name' => $targetVersion->version_name,
                    'schema_version' => $targetVersion->schema_version,
                    'is_current' => true,
                ],
                'model_version_id' => $targetVersion->id,
                'model_version_name' => $targetVersion->version_name,
            ], 200),
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.models.retrain.activate', $targetVersion));

        $response
            ->assertRedirect(route('admin.models.retrain'))
            ->assertSessionHas('admin_notice');

        Http::assertSent(function ($request) use ($targetVersion): bool {
            return $request->url() === 'http://flask-api:5000/api/models/activate'
                && ($request['model_version_id'] ?? null) === $targetVersion->id;
        });

        $this->assertDatabaseHas('model_versions', [
            'id' => $currentVersion->id,
            'version_name' => 'ready-schema-v1-current',
        ]);
    }

    public function test_admin_review_page_renders_raw_data_and_model_layers(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $application = StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'online_student',
            'applicant_name' => 'Salsa Maharani',
            'applicant_email' => 'salsa@example.com',
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 900000,
            'penghasilan_ibu_rupiah' => 400000,
            'penghasilan_gabungan_rupiah' => 1300000,
            'jumlah_tanggungan_raw' => 5,
            'anak_ke_raw' => 3,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Sewa',
            'daya_listrik_text' => '900 VA',
            'status' => 'Submitted',
        ]);

        $encoding = ApplicationFeatureEncoding::query()->create([
            'application_id' => $application->id,
            'schema_version' => 1,
            'encoding_version' => 1,
            'is_current' => true,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_gabungan' => 2,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 2,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'encoded_at' => now(),
        ]);

        ApplicationModelSnapshot::query()->create([
            'application_id' => $application->id,
            'encoding_id' => $encoding->id,
            'schema_version' => 1,
            'model_ready' => true,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_gabungan' => 2,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 2,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'catboost_label' => 'Layak',
            'catboost_confidence' => 0.82,
            'naive_bayes_label' => 'Indikasi',
            'naive_bayes_confidence' => 0.71,
            'disagreement_flag' => true,
            'final_recommendation' => 'Layak',
            'review_priority' => 'high',
            'rule_score' => 0.66,
            'rule_recommendation' => 'Layak',
            'snapshotted_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.applications.show', $application));

        $response
            ->assertOk()
            ->assertSee('Detail Pengajuan')
            ->assertSee('Salsa Maharani')
            ->assertSee('Analisis Rekomendasi Sistem')
            ->assertSee('Data Mentah Mahasiswa')
            ->assertSee('Informasi Pengajuan')
            ->assertDontSee('Hasil Encoding Fitur');
    }

    public function test_admin_cannot_finalize_from_review_page_before_prediction_snapshot_exists(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $application = StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'online_student',
            'applicant_name' => 'Rara Anindita',
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 800000,
            'penghasilan_ibu_rupiah' => 100000,
            'penghasilan_gabungan_rupiah' => 900000,
            'jumlah_tanggungan_raw' => 6,
            'anak_ke_raw' => 4,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Menumpang',
            'daya_listrik_text' => '450 VA',
            'status' => 'Submitted',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.applications.verify', $application), [
                'note' => 'Seharusnya ditahan sebelum ada rekomendasi.',
            ]);

        $response
            ->assertRedirect(route('admin.applications.show', $application))
            ->assertSessionHas('admin_notice');

        $this->assertDatabaseHas('student_applications', [
            'id' => $application->id,
            'status' => 'Submitted',
            'admin_decision' => null,
        ]);

        $this->assertDatabaseCount('spk_training_data', 0);
    }

    public function test_admin_can_generate_prediction_and_finalize_from_review_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $application = StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'online_student',
            'applicant_name' => 'Dina Safitri',
            'applicant_email' => 'dina@example.com',
            'kip' => 1,
            'pkh' => 1,
            'kks' => 1,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 600000,
            'penghasilan_ibu_rupiah' => 300000,
            'penghasilan_gabungan_rupiah' => 900000,
            'jumlah_tanggungan_raw' => 6,
            'anak_ke_raw' => 5,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Menumpang',
            'daya_listrik_text' => '450 VA',
            'status' => 'Submitted',
        ]);

        Http::fake([
            'http://flask-api:5000/api/predict' => Http::response([
                'status' => 'success',
                'model_version_id' => 12,
                'model_version_name' => 'ready-schema-v1-demo',
                'model_results' => [
                    'catboost' => ['label' => 'Layak', 'confidence' => 0.86],
                    'naive_bayes' => ['label' => 'Layak', 'confidence' => 0.75],
                    'disagreement_flag' => false,
                    'final_recommendation' => 'Layak',
                    'review_priority' => 'normal',
                    'model_ready' => true,
                ],
            ], 200),
        ]);

        $refreshResponse = $this
            ->actingAs($admin)
            ->post(route('admin.applications.refresh-prediction', $application));

        $refreshResponse
            ->assertRedirect(route('admin.applications.show', $application))
            ->assertSessionHas('admin_notice');

        $encoding = ApplicationFeatureEncoding::query()->where('application_id', $application->id)->firstOrFail();

        $this->assertDatabaseHas('application_model_snapshots', [
            'application_id' => $application->id,
            'encoding_id' => $encoding->id,
            'final_recommendation' => 'Layak',
            'model_version_id' => 12,
        ]);

        $verifyResponse = $this
            ->actingAs($admin)
            ->post(route('admin.applications.verify', $application), [
                'note' => 'Rekomendasi model sesuai dengan data mentah dan dokumen.',
            ]);

        $verifyResponse
            ->assertRedirect(route('admin.applications.show', $application))
            ->assertSessionHas('admin_notice');

        $this->assertDatabaseHas('student_applications', [
            'id' => $application->id,
            'status' => 'Verified',
            'admin_decision' => 'Verified',
        ]);

        $this->assertDatabaseHas('spk_training_data', [
            'source_application_id' => $application->id,
            'source_encoding_id' => $encoding->id,
            'label' => 'Layak',
            'label_class' => 0,
        ]);
    }

    public function test_admin_training_correction_page_renders_current_training_row(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $application = StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Rizky Fadillah',
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 1,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 500000,
            'penghasilan_ibu_rupiah' => 300000,
            'penghasilan_gabungan_rupiah' => 800000,
            'jumlah_tanggungan_raw' => 6,
            'anak_ke_raw' => 4,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Menumpang',
            'daya_listrik_text' => '450 VA',
            'status' => 'Verified',
            'admin_decision' => 'Verified',
            'admin_decided_by' => $admin->id,
            'admin_decided_at' => now(),
        ]);

        $encoding = ApplicationFeatureEncoding::query()->create([
            'application_id' => $application->id,
            'schema_version' => 1,
            'encoding_version' => 1,
            'is_current' => true,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 1,
            'sktm' => 1,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 1,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'encoded_at' => now(),
        ]);

        SpkTrainingData::query()->create([
            'source_application_id' => $application->id,
            'source_encoding_id' => $encoding->id,
            'schema_version' => 1,
            'encoding_version' => 1,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 1,
            'sktm' => 1,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 1,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'label' => 'Layak',
            'label_class' => 0,
            'decision_status' => 'Verified',
            'finalized_by_user_id' => $admin->id,
            'finalized_at' => now(),
            'is_active' => true,
            'admin_corrected' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.training-data.show', $application));

        $response
            ->assertOk()
            ->assertSee('Koreksi Data Training')
            ->assertSee('Rizky Fadillah')
            ->assertSee('Penghasilan Gabungan')
            ->assertSee('Simpan Koreksi Training');
    }

    public function test_admin_can_update_training_row_from_web_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $application = StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Rizky Fadillah',
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 1,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 500000,
            'penghasilan_ibu_rupiah' => 300000,
            'penghasilan_gabungan_rupiah' => 800000,
            'jumlah_tanggungan_raw' => 6,
            'anak_ke_raw' => 4,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => 'Menumpang',
            'daya_listrik_text' => '450 VA',
            'status' => 'Verified',
            'admin_decision' => 'Verified',
            'admin_decided_by' => $admin->id,
            'admin_decided_at' => now(),
        ]);

        $encoding = ApplicationFeatureEncoding::query()->create([
            'application_id' => $application->id,
            'schema_version' => 1,
            'encoding_version' => 1,
            'is_current' => true,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 1,
            'sktm' => 1,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 1,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'encoded_at' => now(),
        ]);

        SpkTrainingData::query()->create([
            'source_application_id' => $application->id,
            'source_encoding_id' => $encoding->id,
            'schema_version' => 1,
            'encoding_version' => 1,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 1,
            'dtks' => 1,
            'sktm' => 1,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 1,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'label' => 'Layak',
            'label_class' => 0,
            'decision_status' => 'Verified',
            'finalized_by_user_id' => $admin->id,
            'finalized_at' => now(),
            'is_active' => true,
            'admin_corrected' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->put(route('admin.training-data.update', $application), [
                'kip' => 1,
                'pkh' => 1,
                'kks' => 1,
                'dtks' => 1,
                'sktm' => 1,
                'penghasilan_gabungan' => 2,
                'penghasilan_ayah' => 2,
                'penghasilan_ibu' => 1,
                'jumlah_tanggungan' => 2,
                'anak_ke' => 2,
                'status_orangtua' => 3,
                'status_rumah' => 2,
                'daya_listrik' => 2,
                'label' => 'Indikasi',
                'correction_note' => 'Dokumen menunjukkan penghasilan gabungan masuk kategori 2.',
            ]);

        $response
            ->assertRedirect(route('admin.training-data.show', $application))
            ->assertSessionHas('admin_notice');

        $this->assertDatabaseHas('spk_training_data', [
            'source_application_id' => $application->id,
            'pkh' => 1,
            'penghasilan_gabungan' => 2,
            'label' => 'Indikasi',
            'label_class' => 1,
            'admin_corrected' => true,
        ]);
    }

    public function test_admin_can_open_house_status_review_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Nadia Prameswari',
            'study_program' => 'Teknik Informatika',
            'faculty' => 'Fakultas Vokasi',
            'source_reference_number' => 'A-001',
            'source_row_number' => 2,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 0,
            'penghasilan_ayah_rupiah' => 1200000,
            'penghasilan_ibu_rupiah' => 400000,
            'penghasilan_gabungan_rupiah' => 1600000,
            'jumlah_tanggungan_raw' => 4,
            'anak_ke_raw' => 2,
            'status_orangtua_text' => 'ayah=hidup; ibu=hidup',
            'status_rumah_text' => null,
            'daya_listrik_text' => '900',
            'status' => 'Verified',
            'admin_decision' => 'Verified',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.applications.house-review'));

        $response
            ->assertOk()
            ->assertSee('Kelengkapan Data Mentah')
            ->assertSee('Nadia Prameswari')
            ->assertSee('data kosong');
    }

    public function test_admin_can_update_house_status_without_populating_training_data(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $application = StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Bagus Prasetyo',
            'source_reference_number' => 'A-010',
            'source_row_number' => 10,
            'kip' => 1,
            'pkh' => 1,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 0,
            'penghasilan_ayah_rupiah' => 900000,
            'penghasilan_ibu_rupiah' => 0,
            'penghasilan_gabungan_rupiah' => 900000,
            'jumlah_tanggungan_raw' => 5,
            'anak_ke_raw' => 3,
            'status_orangtua_text' => 'Lengkap',
            'status_rumah_text' => null,
            'daya_listrik_text' => '450 VA',
            'status' => 'Verified',
            'admin_decision' => 'Verified',
            'admin_decided_by' => $admin->id,
            'admin_decided_at' => now(),
        ]);

        $encoding = ApplicationFeatureEncoding::query()->create([
            'application_id' => $application->id,
            'schema_version' => 1,
            'encoding_version' => 1,
            'is_current' => true,
            'kip' => 1,
            'pkh' => 1,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 0,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 2,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'encoded_at' => now(),
        ]);

        ApplicationModelSnapshot::query()->create([
            'application_id' => $application->id,
            'encoding_id' => $encoding->id,
            'schema_version' => 1,
            'kip' => 1,
            'pkh' => 1,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 0,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 2,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'model_ready' => true,
            'catboost_label' => 'Layak',
            'catboost_confidence' => 0.8,
            'naive_bayes_label' => 'Layak',
            'naive_bayes_confidence' => 0.7,
            'disagreement_flag' => false,
            'final_recommendation' => 'Layak',
            'review_priority' => 'normal',
            'snapshotted_at' => now(),
        ]);

        SpkTrainingData::query()->create([
            'source_application_id' => $application->id,
            'source_encoding_id' => $encoding->id,
            'schema_version' => 1,
            'encoding_version' => 1,
            'kip' => 1,
            'pkh' => 1,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 0,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 2,
            'anak_ke' => 2,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'label' => 'Layak',
            'label_class' => 0,
            'decision_status' => 'Verified',
            'finalized_by_user_id' => $admin->id,
            'finalized_at' => now(),
            'is_active' => true,
            'admin_corrected' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->put(route('admin.applications.house-review.update', $application), [
                'status_rumah_text' => 'Milik sendiri',
            ]);

        $response
            ->assertRedirect(route('admin.applications.house-review'))
            ->assertSessionHas('admin_notice');

        $this->assertDatabaseHas('student_applications', [
            'id' => $application->id,
            'status_rumah_text' => 'Milik sendiri',
        ]);

        $this->assertDatabaseMissing('application_feature_encodings', [
            'id' => $encoding->id,
        ]);

        $this->assertDatabaseMissing('application_model_snapshots', [
            'application_id' => $application->id,
        ]);

        $this->assertDatabaseMissing('spk_training_data', [
            'source_application_id' => $application->id,
        ]);

        $this->assertDatabaseHas('application_status_logs', [
            'application_id' => $application->id,
            'action' => 'updated_house_status',
        ]);
    }

    public function test_admin_can_batch_update_house_statuses_from_one_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $applicationWithArtifacts = StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Dina Safitri',
            'source_reference_number' => 'A-020',
            'source_row_number' => 20,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 900000,
            'penghasilan_ibu_rupiah' => 0,
            'penghasilan_gabungan_rupiah' => 900000,
            'jumlah_tanggungan_raw' => 4,
            'anak_ke_raw' => 2,
            'status_orangtua_text' => 'ayah=hidup; ibu=hidup',
            'status_rumah_text' => null,
            'daya_listrik_text' => '450 VA',
            'status' => 'Verified',
            'admin_decision' => 'Verified',
            'admin_decided_by' => $admin->id,
            'admin_decided_at' => now(),
        ]);

        $encoding = ApplicationFeatureEncoding::query()->create([
            'application_id' => $applicationWithArtifacts->id,
            'schema_version' => 1,
            'encoding_version' => 1,
            'is_current' => true,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 2,
            'anak_ke' => 3,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'encoded_at' => now(),
        ]);

        ApplicationModelSnapshot::query()->create([
            'application_id' => $applicationWithArtifacts->id,
            'encoding_id' => $encoding->id,
            'schema_version' => 1,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 2,
            'anak_ke' => 3,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'model_ready' => true,
            'catboost_label' => 'Layak',
            'catboost_confidence' => 0.7,
            'naive_bayes_label' => 'Layak',
            'naive_bayes_confidence' => 0.6,
            'disagreement_flag' => false,
            'final_recommendation' => 'Layak',
            'review_priority' => 'normal',
            'snapshotted_at' => now(),
        ]);

        SpkTrainingData::query()->create([
            'source_application_id' => $applicationWithArtifacts->id,
            'source_encoding_id' => $encoding->id,
            'schema_version' => 1,
            'encoding_version' => 1,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 1,
            'penghasilan_gabungan' => 1,
            'penghasilan_ayah' => 1,
            'penghasilan_ibu' => 1,
            'jumlah_tanggungan' => 2,
            'anak_ke' => 3,
            'status_orangtua' => 3,
            'status_rumah' => 2,
            'daya_listrik' => 2,
            'label' => 'Layak',
            'label_class' => 0,
            'decision_status' => 'Verified',
            'finalized_by_user_id' => $admin->id,
            'finalized_at' => now(),
            'is_active' => true,
            'admin_corrected' => false,
        ]);

        $applicationWithoutArtifacts = StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Rani Kusuma',
            'source_reference_number' => 'A-021',
            'source_row_number' => 21,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 0,
            'penghasilan_ayah_rupiah' => 1200000,
            'penghasilan_ibu_rupiah' => 400000,
            'penghasilan_gabungan_rupiah' => 1600000,
            'jumlah_tanggungan_raw' => 5,
            'anak_ke_raw' => 2,
            'status_orangtua_text' => 'ayah=hidup; ibu=hidup',
            'status_rumah_text' => null,
            'daya_listrik_text' => '900',
            'status' => 'Rejected',
            'admin_decision' => 'Rejected',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.applications.house-review.batch-update'), [
                'q' => '',
                'house_state' => 'missing',
                'applications' => [
                    [
                        'id' => $applicationWithArtifacts->id,
                        'status_rumah_text' => 'Milik sendiri',
                    ],
                    [
                        'id' => $applicationWithoutArtifacts->id,
                        'status_rumah_text' => 'Menumpang',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('admin.applications.house-review', ['house_state' => 'missing']))
            ->assertSessionHas('admin_notice');

        $this->assertDatabaseHas('student_applications', [
            'id' => $applicationWithArtifacts->id,
            'status_rumah_text' => 'Milik sendiri',
        ]);

        $this->assertDatabaseHas('student_applications', [
            'id' => $applicationWithoutArtifacts->id,
            'status_rumah_text' => 'Menumpang',
        ]);

        $this->assertDatabaseMissing('application_feature_encodings', [
            'id' => $encoding->id,
        ]);

        $this->assertDatabaseMissing('application_model_snapshots', [
            'application_id' => $applicationWithArtifacts->id,
        ]);

        $this->assertDatabaseMissing('spk_training_data', [
            'source_application_id' => $applicationWithArtifacts->id,
        ]);

        $this->assertDatabaseCount('application_status_logs', 2);
        $this->assertDatabaseHas('application_status_logs', [
            'application_id' => $applicationWithArtifacts->id,
            'action' => 'updated_house_status',
        ]);
        $this->assertDatabaseHas('application_status_logs', [
            'application_id' => $applicationWithoutArtifacts->id,
            'action' => 'updated_house_status',
        ]);
    }

    public function test_admin_can_batch_update_missing_raw_applicant_data_from_one_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $application = StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Salsa Maharani',
            'source_reference_number' => 'A-030',
            'source_row_number' => 30,
            'kip' => 0,
            'pkh' => 0,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 0,
            'penghasilan_ayah_rupiah' => null,
            'penghasilan_ibu_rupiah' => null,
            'penghasilan_gabungan_rupiah' => null,
            'jumlah_tanggungan_raw' => null,
            'anak_ke_raw' => null,
            'status_orangtua_text' => null,
            'status_rumah_text' => null,
            'daya_listrik_text' => null,
            'status' => 'Submitted',
            'admin_decision' => null,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.applications.house-review.batch-update'), [
                'q' => '',
                'house_state' => 'missing',
                'applications' => [
                    [
                        'id' => $application->id,
                        'kip' => 1,
                        'pkh' => 1,
                        'kks' => 0,
                        'dtks' => 1,
                        'sktm' => 1,
                        'penghasilan_ayah_rupiah' => 1500000,
                        'penghasilan_ibu_rupiah' => 500000,
                        'jumlah_tanggungan_raw' => 4,
                        'anak_ke_raw' => 2,
                        'status_orangtua_text' => 'ayah=hidup; ibu=hidup',
                        'status_rumah_text' => 'Sewa',
                        'daya_listrik_text' => '900',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('admin.applications.house-review', ['house_state' => 'missing']))
            ->assertSessionHas('admin_notice');

        $this->assertDatabaseHas('student_applications', [
            'id' => $application->id,
            'kip' => 1,
            'pkh' => 1,
            'dtks' => 1,
            'sktm' => 1,
            'penghasilan_ayah_rupiah' => 1500000,
            'penghasilan_ibu_rupiah' => 500000,
            'penghasilan_gabungan_rupiah' => 2000000,
            'jumlah_tanggungan_raw' => 4,
            'anak_ke_raw' => 2,
            'status_orangtua_text' => 'ayah=hidup; ibu=hidup',
            'status_rumah_text' => 'Sewa',
            'daya_listrik_text' => '900',
        ]);

        $this->assertDatabaseHas('application_status_logs', [
            'application_id' => $application->id,
            'action' => 'updated_raw_applicant_data',
        ]);
    }

    public function test_csv_import_preserves_existing_house_status_when_incoming_value_is_blank(): void
    {
        $application = StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Applicant Existing',
            'source_sheet_name' => 'Verif KIP SNBP 2023',
            'source_row_number' => 200,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 0,
            'penghasilan_ayah_rupiah' => 1000000,
            'penghasilan_ibu_rupiah' => 0,
            'penghasilan_gabungan_rupiah' => 1000000,
            'jumlah_tanggungan_raw' => 3,
            'anak_ke_raw' => 1,
            'status_orangtua_text' => 'ayah=hidup; ibu=hidup',
            'status_rumah_text' => 'Milik sendiri',
            'daya_listrik_text' => '900',
            'status' => 'Verified',
            'admin_decision' => 'Verified',
        ]);

        $csvPath = $this->makeOfflineImportCsv([
            [
                'source_row_number' => 200,
                'applicant_name' => 'Applicant Existing Updated',
                'status_rumah_text' => '',
                'status' => 'Rejected',
                'admin_decision' => 'Rejected',
            ],
        ]);

        /** @var CsvStudentApplicationImporter $importer */
        $importer = app(CsvStudentApplicationImporter::class);
        $result = $importer->import($csvPath, false);

        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['preserved_house_status']);

        $application->refresh();

        $this->assertSame('Applicant Existing Updated', $application->applicant_name);
        $this->assertSame('Milik sendiri', $application->status_rumah_text);
        $this->assertSame('Rejected', $application->status);
    }

    public function test_csv_import_refresh_preserves_existing_house_status_when_incoming_value_is_blank(): void
    {
        StudentApplication::query()->create([
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'applicant_name' => 'Applicant Existing',
            'source_sheet_name' => 'Verif KIP SNBP 2023',
            'source_row_number' => 201,
            'kip' => 1,
            'pkh' => 0,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 0,
            'penghasilan_ayah_rupiah' => 1000000,
            'penghasilan_ibu_rupiah' => 0,
            'penghasilan_gabungan_rupiah' => 1000000,
            'jumlah_tanggungan_raw' => 3,
            'anak_ke_raw' => 1,
            'status_orangtua_text' => 'ayah=hidup; ibu=hidup',
            'status_rumah_text' => 'Sewa',
            'daya_listrik_text' => '900',
            'status' => 'Verified',
            'admin_decision' => 'Verified',
        ]);

        $csvPath = $this->makeOfflineImportCsv([
            [
                'source_row_number' => 201,
                'applicant_name' => 'Applicant Refreshed',
                'status_rumah_text' => '',
                'status' => 'Verified',
                'admin_decision' => 'Verified',
            ],
        ]);

        /** @var CsvStudentApplicationImporter $importer */
        $importer = app(CsvStudentApplicationImporter::class);
        $result = $importer->import($csvPath, true);

        $this->assertSame(1, $result['deleted_existing']);
        $this->assertSame(1, $result['inserted']);
        $this->assertSame(1, $result['preserved_house_status']);

        $application = StudentApplication::query()
            ->where('source_sheet_name', 'Verif KIP SNBP 2023')
            ->where('source_row_number', 201)
            ->firstOrFail();

        $this->assertSame('Applicant Refreshed', $application->applicant_name);
        $this->assertSame('Sewa', $application->status_rumah_text);
    }

    /**
     * @param  list<array<string, mixed>>  $overrides
     */
    private function makeOfflineImportCsv(array $overrides): string
    {
        $headers = [
            'schema_version',
            'submission_source',
            'student_user_id',
            'applicant_name',
            'applicant_email',
            'study_program',
            'faculty',
            'source_reference_number',
            'source_document_link',
            'source_sheet_name',
            'source_row_number',
            'source_label_text',
            'kip',
            'pkh',
            'kks',
            'dtks',
            'sktm',
            'penghasilan_ayah_rupiah',
            'penghasilan_ibu_rupiah',
            'penghasilan_gabungan_rupiah',
            'jumlah_tanggungan_raw',
            'anak_ke_raw',
            'status_orangtua_text',
            'status_rumah_text',
            'daya_listrik_text',
            'status',
            'admin_decision',
            'admin_decision_note',
            'manual_review_required',
            'manual_house_review',
            'cleaning_notes',
        ];

        $defaultRow = [
            'schema_version' => 1,
            'submission_source' => 'offline_admin_import',
            'student_user_id' => '',
            'applicant_name' => 'Applicant',
            'applicant_email' => '',
            'study_program' => 'Teknik Informatika',
            'faculty' => 'Fakultas Vokasi',
            'source_reference_number' => 'REF-001',
            'source_document_link' => 'https://example.test/doc.pdf',
            'source_sheet_name' => 'Verif KIP SNBP 2023',
            'source_row_number' => 2,
            'source_label_text' => 'layak',
            'kip' => 1,
            'pkh' => 0,
            'kks' => 0,
            'dtks' => 0,
            'sktm' => 0,
            'penghasilan_ayah_rupiah' => 1000000,
            'penghasilan_ibu_rupiah' => 0,
            'penghasilan_gabungan_rupiah' => 1000000,
            'jumlah_tanggungan_raw' => 3,
            'anak_ke_raw' => 1,
            'status_orangtua_text' => 'ayah=hidup; ibu=hidup',
            'status_rumah_text' => 'Menumpang',
            'daya_listrik_text' => '900',
            'status' => 'Verified',
            'admin_decision' => 'Verified',
            'admin_decision_note' => 'Imported for test',
            'manual_review_required' => 0,
            'manual_house_review' => 0,
            'cleaning_notes' => '',
        ];

        $tempPath = tempnam(sys_get_temp_dir(), 'kip-import-');
        if ($tempPath === false) {
            $this->fail('Gagal membuat file CSV sementara untuk test import.');
        }

        $handle = fopen($tempPath, 'wb');
        if ($handle === false) {
            $this->fail('Gagal membuka file CSV sementara untuk test import.');
        }

        fputcsv($handle, $headers);

        foreach ($overrides as $rowOverrides) {
            $row = array_merge($defaultRow, $rowOverrides);
            fputcsv($handle, array_map(static fn (string $header): mixed => $row[$header] ?? '', $headers));
        }

        fclose($handle);

        return $tempPath;
    }
}
