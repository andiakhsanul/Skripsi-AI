<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationModelSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'schema_version',
        'model_version_id',
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
        'model_ready',
        'catboost_label',
        'catboost_confidence',
        'naive_bayes_label',
        'naive_bayes_confidence',
        'disagreement_flag',
        'final_recommendation',
        'review_priority',
        'rule_score',
        'rule_recommendation',
        'snapshotted_at',
    ];

    protected function casts(): array
    {
        return [
            'model_version_id' => 'integer',
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
            'model_ready' => 'boolean',
            'disagreement_flag' => 'boolean',
            'rule_score' => 'float',
            'snapshotted_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(StudentApplication::class, 'application_id');
    }

    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(ModelVersion::class, 'model_version_id');
    }
}
