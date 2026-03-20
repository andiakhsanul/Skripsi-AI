<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParameterSchemaVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'version',
        'source_file_name',
        'parameter_definitions',
        'is_active',
        'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'parameter_definitions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
