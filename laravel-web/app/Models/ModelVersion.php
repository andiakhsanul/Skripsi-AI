<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'version_name',
        'schema_version',
        'status',
        'triggered_by_user_id',
        'triggered_by_email',
        'training_table',
        'primary_model',
        'secondary_model',
        'dataset_rows_total',
        'rows_used',
        'train_rows',
        'validation_rows',
        'validation_strategy',
        'class_distribution',
        'catboost_artifact_path',
        'naive_bayes_artifact_path',
        'catboost_train_accuracy',
        'catboost_validation_accuracy',
        'naive_bayes_train_accuracy',
        'naive_bayes_validation_accuracy',
        'note',
        'error_message',
        'trained_at',
    ];

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'triggered_by_user_id' => 'integer',
            'dataset_rows_total' => 'integer',
            'rows_used' => 'integer',
            'train_rows' => 'integer',
            'validation_rows' => 'integer',
            'class_distribution' => 'array',
            'catboost_train_accuracy' => 'float',
            'catboost_validation_accuracy' => 'float',
            'naive_bayes_train_accuracy' => 'float',
            'naive_bayes_validation_accuracy' => 'float',
            'trained_at' => 'datetime',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ApplicationModelSnapshot::class, 'model_version_id');
    }
}
