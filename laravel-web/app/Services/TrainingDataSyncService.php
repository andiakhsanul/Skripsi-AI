<?php

namespace App\Services;

use App\Models\StudentApplication;
use Illuminate\Support\Facades\DB;

class TrainingDataSyncService
{
    public function syncFromApplication(StudentApplication $application): void
    {
        if (! in_array($application->status, ['Verified', 'Rejected'], true)) {
            return;
        }

        $label = $application->status === 'Verified' ? 'Layak' : 'Indikasi';
        $labelClass = $label === 'Indikasi' ? 1 : 0;

        DB::table('spk_training_data')->updateOrInsert(
            ['source_application_id' => $application->id],
            [
                'kip_sma' => $application->kip,
                'kip' => $application->kip,
                'pkh' => $application->pkh,
                'kks' => $application->kks,
                'dtks' => $application->dtks,
                'sktm' => $application->sktm,
                'penghasilan_gabungan' => $application->penghasilan_gabungan,
                'penghasilan_ayah' => $application->penghasilan_ayah,
                'penghasilan_ibu' => $application->penghasilan_ibu,
                'jumlah_tanggungan' => $application->jumlah_tanggungan,
                'anak_ke' => $application->anak_ke,
                'status_orangtua' => $application->status_orangtua,
                'status_rumah' => $application->status_rumah,
                'daya_listrik' => $application->daya_listrik,
                'label' => $label,
                'label_class' => $labelClass,
                'schema_version' => $application->schema_version,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
