<?php

namespace App\Services;

use App\Models\ApplicationModelSnapshot;
use App\Models\StudentApplication;
use Illuminate\Support\Facades\DB;

/**
 * TrainingDataSyncService
 *
 * Menyinkronisasi data final yang sudah disetujui admin ke spk_training_data.
 * Dipanggil otomatis saat admin melakukan Verify atau Reject.
 *
 * Tabel spk_training_data hanya berisi 13 fitur encoded (0/1 dan 1/2/3)
 * dan label hasil keputusan admin untuk proses retrain model.
 */
class TrainingDataSyncService
{
    public function syncFromApplication(StudentApplication $application): void
    {
        if (! in_array($application->status, ['Verified', 'Rejected'], true)) {
            return;
        }

        /** @var ApplicationModelSnapshot|StudentApplication $encodedSource */
        $encodedSource = $application->modelSnapshot()->first() ?? $application;

        // Verified = Layak (0), Rejected = Indikasi (1)
        $label      = $application->status === 'Verified' ? 'Layak' : 'Indikasi';
        $labelClass = $label === 'Indikasi' ? 1 : 0;

        $payload = [
            'schema_version' => $application->schema_version,
            'label' => $label,
            'label_class' => $labelClass,
            'is_active' => true,
            'admin_corrected' => false,
            'correction_note' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ];

        foreach (StudentApplication::featureColumns() as $column) {
            $payload[$column] = $encodedSource->{$column};
        }

        DB::table('spk_training_data')->updateOrInsert(
            ['source_application_id' => $application->id],
            $payload,
        );
    }
}
