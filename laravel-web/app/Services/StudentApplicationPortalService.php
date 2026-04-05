<?php

namespace App\Services;

use App\Models\StudentApplication;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;

class StudentApplicationPortalService
{
    /**
     * @return array<string, mixed>
     */
    public function formOptions(): array
    {
        return [
            'binary' => [
                ['value' => 1, 'label' => 'Ya'],
                ['value' => 0, 'label' => 'Tidak'],
            ],
            'status_orangtua' => [
                'Lengkap',
                'Yatim',
                'Piatu',
                'Yatim Piatu',
            ],
            'status_rumah' => [
                'Tidak memiliki rumah',
                'Sewa / Kontrak',
                'Menumpang',
                'Milik sendiri',
            ],
            'daya_listrik' => [
                'Tidak ada / Non-PLN',
                '450 VA',
                '900 VA',
                '1300 VA',
                '2200 VA atau lebih',
            ],
        ];
    }

    public function detail(User $student, int $applicationId): StudentApplication
    {
        return StudentApplication::query()
            ->with(['logs.actor:id,name', 'modelSnapshot.modelVersion'])
            ->where('student_user_id', $student->id)
            ->findOrFail($applicationId);
    }

    /**
     * @throws AuthorizationException
     */
    public function editable(User $student, int $applicationId): StudentApplication
    {
        $application = $this->detail($student, $applicationId);

        if (! $application->canBeRevisedByStudent()) {
            throw new AuthorizationException('Pengajuan ini sudah tidak dapat direvisi.');
        }

        return $application;
    }

    public function documentUrl(StudentApplication $application): ?string
    {
        if ($application->submitted_pdf_path) {
            return Storage::disk('public')->url($application->submitted_pdf_path);
        }

        return $application->source_document_link;
    }

    public function canEdit(StudentApplication $application): bool
    {
        return $application->canBeRevisedByStudent();
    }
}
