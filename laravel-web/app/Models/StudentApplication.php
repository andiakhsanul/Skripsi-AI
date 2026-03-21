<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        // Identitas & versi
        'student_user_id',
        'schema_version',

        // ── Nilai RAW (input asli mahasiswa, sebelum encoding) ──
        'penghasilan_gabungan_raw',
        'penghasilan_ayah_raw',
        'penghasilan_ibu_raw',
        'jumlah_tanggungan_raw',
        'anak_ke_raw',
        'status_orangtua_raw',
        'status_rumah_raw',
        'daya_listrik_raw',

        // ── Nilai Encoded (1/2/3 atau 0/1, hasil EncodingService) ──
        'kip',
        'pkh',
        'kks',
        'dtks',
        'sktm',
        'penghasilan_gabungan',
        'penghasilan_ayah',
        'penghasilan_ibu',
        'jumlah_tanggungan',
        'anak_ke',
        'status_orangtua',
        'status_rumah',
        'daya_listrik',

        // Parameter tambahan & PDF
        'parameters_extra',
        'ditmawa_pdf_path',
        'ditmawa_pdf_uploaded_at',

        // Output AI
        'status',
        'model_ready',
        'catboost_label',
        'catboost_confidence',
        'naive_bayes_label',
        'naive_bayes_confidence',
        'disagreement_flag',
        'final_recommendation',
        'review_priority',

        // Rule-based scoring
        'rule_score',
        'rule_recommendation',

        // Link dokumen
        'document_submission_link',
        'supporting_document_url',
        'supporting_document_path',

        // Keputusan admin
        'admin_decision',
        'admin_decided_by',
        'admin_decision_note',
        'admin_decided_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters_extra'       => 'array',
            'model_ready'            => 'boolean',
            'disagreement_flag'      => 'boolean',
            'rule_score'             => 'float',

            // Binary encoded (0/1)
            'kip'   => 'integer',
            'pkh'   => 'integer',
            'kks'   => 'integer',
            'dtks'  => 'integer',
            'sktm'  => 'integer',

            // Ordinal encoded (1/2/3)
            'penghasilan_gabungan' => 'integer',
            'penghasilan_ayah'     => 'integer',
            'penghasilan_ibu'      => 'integer',
            'jumlah_tanggungan'    => 'integer',
            'anak_ke'              => 'integer',
            'status_orangtua'      => 'integer',
            'status_rumah'         => 'integer',
            'daya_listrik'         => 'integer',

            // Raw numerik
            'penghasilan_gabungan_raw' => 'integer',
            'penghasilan_ayah_raw'     => 'integer',
            'penghasilan_ibu_raw'      => 'integer',
            'jumlah_tanggungan_raw'    => 'integer',
            'anak_ke_raw'              => 'integer',

            'ditmawa_pdf_uploaded_at' => 'datetime',
            'admin_decided_at'        => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function adminDecider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_decided_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApplicationStatusLog::class, 'application_id');
    }

    /**
     * Apakah pengajuan sudah upload PDF Ditmawa?
     */
    public function hasDitmawaPdf(): bool
    {
        return ! empty($this->ditmawa_pdf_path);
    }
}
