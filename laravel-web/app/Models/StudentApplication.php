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
        'student_user_id',
        'schema_version',
        'kip_sma',
        'penghasilan_gabungan',
        'daya_listrik',
        'parameters_extra',
        'status',
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
        'document_submission_link',
        'supporting_document_url',
        'supporting_document_path',
        'admin_decision',
        'admin_decided_by',
        'admin_decision_note',
        'admin_decided_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters_extra' => 'array',
            'model_ready' => 'boolean',
            'disagreement_flag' => 'boolean',
            'rule_score' => 'float',
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
}
