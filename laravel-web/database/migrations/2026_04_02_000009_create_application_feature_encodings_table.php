<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function encodedFeatureColumns(): array
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

    public function up(): void
    {
        Schema::create('application_feature_encodings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('student_applications')->cascadeOnDelete();
            $table->unsignedInteger('schema_version')->default(1);
            $table->unsignedInteger('encoding_version')->default(1);
            $table->foreignId('encoded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_current')->default(true);
            $table->json('validation_errors')->nullable();

            foreach ($this->encodedFeatureColumns() as $column) {
                $table->unsignedTinyInteger($column);
            }

            $table->timestamp('encoded_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'schema_version', 'encoding_version'], 'app_feature_encodings_version_unique');
            $table->index(['application_id', 'is_current']);
            $table->index(['schema_version', 'encoding_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_feature_encodings');
    }
};
