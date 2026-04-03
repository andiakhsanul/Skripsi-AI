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

    /**
     * @return list<string>
     */
    public static function featureColumns(): array
    {
        return [
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
        ];
    }

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
        'penghasilan_gabungan',
        'penghasilan_ayah',
        'penghasilan_ibu',
        'penghasilan_gabungan_rupiah',
        'penghasilan_ayah_rupiah',
        'penghasilan_ibu_rupiah',
        'jumlah_tanggungan',
        'jumlah_tanggungan_raw',
        'anak_ke',
        'anak_ke_raw',
        'status_orangtua',
        'status_orangtua_text',
        'status_rumah',
        'status_rumah_text',
        'daya_listrik',
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
            'kip' => 'integer',
            'pkh' => 'integer',
            'kks' => 'integer',
            'dtks' => 'integer',
            'sktm' => 'integer',
            'penghasilan_gabungan' => 'integer',
            'penghasilan_ayah' => 'integer',
            'penghasilan_ibu' => 'integer',
            'penghasilan_gabungan_rupiah' => 'integer',
            'penghasilan_ayah_rupiah' => 'integer',
            'penghasilan_ibu_rupiah' => 'integer',
            'jumlah_tanggungan' => 'integer',
            'jumlah_tanggungan_raw' => 'integer',
            'anak_ke' => 'integer',
            'anak_ke_raw' => 'integer',
            'status_orangtua' => 'integer',
            'status_rumah' => 'integer',
            'daya_listrik' => 'integer',
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

    public function hasSubmittedPdf(): bool
    {
        return ! empty($this->submitted_pdf_path);
    }
}
