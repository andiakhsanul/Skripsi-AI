<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpkTrainingData extends Model
{
    use HasFactory;

    protected $table = 'spk_training_data';

    protected $fillable = [
        'source_application_id',
        'source_encoding_id',
        'schema_version',
        'encoding_version',
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
        'label',
        'label_class',
        'decision_status',
        'finalized_by_user_id',
        'finalized_at',
        'is_active',
        'admin_corrected',
        'correction_note',
    ];

    protected function casts(): array
    {
        return [
            'source_application_id' => 'integer',
            'source_encoding_id' => 'integer',
            'schema_version' => 'integer',
            'encoding_version' => 'integer',
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
            'label_class' => 'integer',
            'finalized_by_user_id' => 'integer',
            'finalized_at' => 'datetime',
            'is_active' => 'boolean',
            'admin_corrected' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(StudentApplication::class, 'source_application_id');
    }

    public function sourceEncoding(): BelongsTo
    {
        return $this->belongsTo(ApplicationFeatureEncoding::class, 'source_encoding_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }
}
