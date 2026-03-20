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

        $label = $application->status === 'Verified' ? 'Layak' : 'Tidak Layak';

        DB::table('spk_training_data')->updateOrInsert(
            ['source_application_id' => $application->id],
            [
                'kip_sma' => $application->kip_sma,
                'penghasilan_gabungan' => $application->penghasilan_gabungan,
                'daya_listrik' => $application->daya_listrik,
                'label' => $label,
                'schema_version' => $application->schema_version,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
