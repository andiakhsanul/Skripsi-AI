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
        private readonly ParameterSchemaService $schemaService,
        private readonly ApplicationInferenceService $applicationInferenceService,
    ) {}

    /**
     * @param array<string, mixed> $validated
     */
    public function submit(User $student, array $validated): StudentApplication
    {
        $schemaVersion = $this->schemaService->resolveSchemaVersion($validated['schema_version'] ?? null);
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

    /**
     * @param array<string, mixed> $validated
     */
    public function update(User $student, StudentApplication $application, array $validated): StudentApplication
    {
        if (! $application->canBeRevisedByStudent()) {
            throw ValidationException::withMessages([
                'application' => ['Pengajuan ini sudah tidak dapat direvisi karena admin telah memprosesnya.'],
            ]);
        }

        $schemaVersion = array_key_exists('schema_version', $validated) && $validated['schema_version'] !== null
            ? $this->schemaService->resolveSchemaVersion((int) $validated['schema_version'])
            : (int) $application->schema_version;
        /** @var UploadedFile|null $replacementPdf */
        $replacementPdf = $validated['submitted_pdf'] ?? null;
        $replacementPdfPath = $replacementPdf?->store('student-application-pdfs', 'public');
        $existingPdfPath = $application->submitted_pdf_path;

        try {
            $updatedApplication = DB::transaction(function () use (
                $student,
                $application,
                $validated,
                $schemaVersion,
                $replacementPdf,
                $replacementPdfPath,
                $existingPdfPath
            ): StudentApplication {
                $application->fill($this->buildPayload($student, $validated, $schemaVersion));

                if ($replacementPdf !== null && $replacementPdfPath !== null) {
                    $application->submitted_pdf_path = $replacementPdfPath;
                    $application->submitted_pdf_original_name = $replacementPdf->getClientOriginalName();
                    $application->submitted_pdf_uploaded_at = now();
                }

                $previousStatus = $application->getOriginal('status');

                $application->save();

                $this->applicationInferenceService->syncPredictionSnapshot($application, $student->id);

                ApplicationStatusLog::query()->create([
                    'application_id' => $application->id,
                    'actor_user_id' => $student->id,
                    'from_status' => $previousStatus,
                    'to_status' => 'Submitted',
                    'action' => 'revised',
                    'metadata' => [
                        'schema_version' => $application->schema_version,
                        'replaced_pdf' => $replacementPdf !== null,
                        'previous_pdf_path' => $existingPdfPath,
                        'submitted_pdf_original_name' => $application->submitted_pdf_original_name,
                    ],
                ]);

                return $application->fresh(['currentEncoding', 'modelSnapshot', 'logs']);
            });

            if ($replacementPdf !== null && $existingPdfPath && $existingPdfPath !== $replacementPdfPath) {
                Storage::disk('public')->delete($existingPdfPath);
            }

            return $updatedApplication;
        } catch (\Throwable $throwable) {
            if ($replacementPdfPath) {
                Storage::disk('public')->delete($replacementPdfPath);
            }

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
