<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_user_id',
        'schema_version',
        'submission_source',
        'applicant_name',
        'applicant_email',
        'study_program',
        'faculty',
        'source_reference_number',
        'source_document_link',
        'source_sheet_name',
        'source_row_number',
        'source_label_text',
        'imported_at',
        'kip',
        'pkh',
        'kks',
        'dtks',
        'sktm',
        'penghasilan_gabungan_rupiah',
        'penghasilan_ayah_rupiah',
        'penghasilan_ibu_rupiah',
        'jumlah_tanggungan_raw',
        'anak_ke_raw',
        'status_orangtua_text',
        'status_rumah_text',
        'daya_listrik_text',
        'submitted_pdf_path',
        'submitted_pdf_original_name',
        'submitted_pdf_uploaded_at',
        'status',
        'admin_decision',
        'admin_decided_by',
        'admin_decision_note',
        'admin_decided_at',
    ];

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'kip' => 'integer',
            'pkh' => 'integer',
            'kks' => 'integer',
            'dtks' => 'integer',
            'sktm' => 'integer',
            'penghasilan_gabungan_rupiah' => 'integer',
            'penghasilan_ayah_rupiah' => 'integer',
            'penghasilan_ibu_rupiah' => 'integer',
            'jumlah_tanggungan_raw' => 'integer',
            'anak_ke_raw' => 'integer',
            'source_row_number' => 'integer',
            'submitted_pdf_uploaded_at' => 'datetime',
            'admin_decided_at' => 'datetime',
            'imported_at' => 'datetime',
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

    public function modelSnapshot(): HasOne
    {
        return $this->hasOne(ApplicationModelSnapshot::class, 'application_id');
    }

    public function featureEncodings(): HasMany
    {
        return $this->hasMany(ApplicationFeatureEncoding::class, 'application_id');
    }

    public function currentEncoding(): HasOne
    {
        return $this->hasOne(ApplicationFeatureEncoding::class, 'application_id')->where('is_current', true);
    }

    public function trainingRows(): HasMany
    {
        return $this->hasMany(SpkTrainingData::class, 'source_application_id');
    }

    public function latestTrainingRow(): HasOne
    {
        return $this->hasOne(SpkTrainingData::class, 'source_application_id')->latestOfMany();
    }

    public function hasSubmittedPdf(): bool
    {
        return ! empty($this->submitted_pdf_path) || ! empty($this->source_document_link);
    }

    public function isOfflineImport(): bool
    {
        return $this->submission_source === 'offline_admin_import';
    }

    public function needsHouseStatusReview(): bool
    {
        return $this->isOfflineImport()
            && blank($this->status_rumah_text);
    }
}
