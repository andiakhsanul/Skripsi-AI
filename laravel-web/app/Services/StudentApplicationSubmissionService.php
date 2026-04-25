<?php

namespace App\Services;

use App\Models\ApplicationStatusLog;
use App\Models\StudentApplication;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentApplicationSubmissionService
{
    public function __construct(
        private readonly ApplicationInferenceService $applicationInferenceService,
    ) {}

    /**
     * @param array<string, mixed> $validated
     */
    public function submit(User $student, array $validated): StudentApplication
    {
        $existing = StudentApplication::query()
            ->where('student_user_id', $student->id)
            ->orderByDesc('id')
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'application' => ['Anda sudah mengajukan KIP-K. Pengajuan hanya dapat dilakukan satu kali — silakan cek status pengajuan Anda.'],
            ]);
        }

        $schemaVersion = 1;
        /** @var UploadedFile $submittedPdf */
        $submittedPdf = $validated['submitted_pdf'];
        $pdfPath = $submittedPdf->store('student-application-pdfs', 'public');

        try {
            return DB::transaction(function () use ($student, $validated, $schemaVersion, $submittedPdf, $pdfPath): StudentApplication {
                $application = StudentApplication::query()->create([
                    ...$this->buildPayload($student, $validated, $schemaVersion),
                    'submitted_pdf_path' => $pdfPath,
                    'submitted_pdf_original_name' => $submittedPdf->getClientOriginalName(),
                    'submitted_pdf_uploaded_at' => now(),
                ]);

                $this->applicationInferenceService->syncPredictionSnapshot($application, $student->id);

                ApplicationStatusLog::query()->create([
                    'application_id' => $application->id,
                    'actor_user_id' => $student->id,
                    'from_status' => null,
                    'to_status' => 'Submitted',
                    'action' => 'submitted',
                    'metadata' => [
                        'schema_version' => $application->schema_version,
                        'submitted_pdf_original_name' => $application->submitted_pdf_original_name,
                    ],
                ]);

                return $application->fresh(['currentEncoding', 'modelSnapshot', 'logs']);
            });
        } catch (\Throwable $throwable) {
            Storage::disk('public')->delete($pdfPath);

            throw $throwable;
        }
    }

    private function resolveCombinedIncome(int $fatherIncome, int $motherIncome): int
    {
        return $fatherIncome + $motherIncome;
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function buildPayload(User $student, array $validated, int $schemaVersion): array
    {
        return [
            'student_user_id' => $student->id,
            'schema_version' => $schemaVersion,
            'submission_source' => 'online_student',
            'applicant_name' => $student->name,
            'applicant_email' => $student->email,
            'study_program' => $validated['study_program'],
            'faculty' => $validated['faculty'],
            'kip' => (int) $validated['kip'],
            'pkh' => (int) $validated['pkh'],
            'kks' => (int) $validated['kks'],
            'dtks' => (int) $validated['dtks'],
            'sktm' => (int) $validated['sktm'],
            'penghasilan_ayah_rupiah' => (int) $validated['penghasilan_ayah_rupiah'],
            'penghasilan_ibu_rupiah' => (int) $validated['penghasilan_ibu_rupiah'],
            'penghasilan_gabungan_rupiah' => $this->resolveCombinedIncome(
                (int) $validated['penghasilan_ayah_rupiah'],
                (int) $validated['penghasilan_ibu_rupiah'],
            ),
            'jumlah_tanggungan_raw' => (int) $validated['jumlah_tanggungan_raw'],
            'anak_ke_raw' => (int) $validated['anak_ke_raw'],
            'status_orangtua_text' => $validated['status_orangtua_text'],
            'status_rumah_text' => $validated['status_rumah_text'],
            'daya_listrik_text' => $validated['daya_listrik_text'],
            'status' => 'Submitted',
        ];
    }
}
