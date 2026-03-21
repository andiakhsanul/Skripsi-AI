<?php

namespace App\Services;

use App\Models\StudentApplication;
use Illuminate\Support\Facades\DB;

/**
 * TrainingDataSyncService
 *
 * Menyinkronisasi data dari student_applications (raw) ke spk_training_data (encoded).
 * Dipanggil otomatis saat admin melakukan Verify atau Reject.
 *
 * Tabel spk_training_data hanya berisi nilai ENCODED (biner 0/1, ordinal 1/2/3)
 * yang digunakan untuk melatih model ML CatBoost dan Naive Bayes.
 */
class TrainingDataSyncService
{
    public function syncFromApplication(StudentApplication $application): void
    {
        if (! in_array($application->status, ['Verified', 'Rejected'], true)) {
            return;
        }

        // Verified = Layak (0), Rejected = Indikasi (1)
        $label      = $application->status === 'Verified' ? 'Layak' : 'Indikasi';
        $labelClass = $label === 'Indikasi' ? 1 : 0;

        DB::table('spk_training_data')->updateOrInsert(
            ['source_application_id' => $application->id],
            [
                // Nilai ENCODED (bukan raw) untuk training
                'kip'   => $application->kip,
                'pkh'   => $application->pkh,
                'kks'   => $application->kks,
                'dtks'  => $application->dtks,
                'sktm'  => $application->sktm,

                'penghasilan_gabungan' => $application->penghasilan_gabungan,
                'penghasilan_ayah'     => $application->penghasilan_ayah,
                'penghasilan_ibu'      => $application->penghasilan_ibu,
                'jumlah_tanggungan'    => $application->jumlah_tanggungan,
                'anak_ke'             => $application->anak_ke,
                'status_orangtua'     => $application->status_orangtua,
                'status_rumah'        => $application->status_rumah,
                'daya_listrik'        => $application->daya_listrik,

                // Label dari keputusan admin
                'label'       => $label,
                'label_class' => $labelClass,

                // Metadata
                'schema_version' => $application->schema_version,
                'is_active'      => true,
                'admin_corrected' => false,
                'correction_note' => null,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );
    }
}
