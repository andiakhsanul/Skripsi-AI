<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApplicationFeatureEncoding extends Model
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
        'application_id',
        'schema_version',
        'encoding_version',
        'encoded_by_user_id',
        'is_current',
        'validation_errors',
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
        'encoded_at',
    ];

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'encoding_version' => 'integer',
            'encoded_by_user_id' => 'integer',
            'is_current' => 'boolean',
            'validation_errors' => 'array',
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
            'encoded_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, int>
     */
    public function toFeatureArray(): array
    {
        $features = [];

        foreach (self::featureColumns() as $column) {
            $features[$column] = (int) $this->{$column};
        }

        return $features;
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(StudentApplication::class, 'application_id');
    }

    public function encodedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by_user_id');
    }

    public function snapshot(): HasOne
    {
        return $this->hasOne(ApplicationModelSnapshot::class, 'encoding_id');
    }

    public function trainingRows(): HasMany
    {
        return $this->hasMany(SpkTrainingData::class, 'source_encoding_id');
    }
}
