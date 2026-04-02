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
            'jumlah_tanggungan' => 'integer',
            'anak_ke' => 'integer',
            'status_orangtua' => 'integer',
            'status_rumah' => 'integer',
            'daya_listrik' => 'integer',
            'submitted_pdf_uploaded_at' => 'datetime',
            'admin_decided_at' => 'datetime',
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
