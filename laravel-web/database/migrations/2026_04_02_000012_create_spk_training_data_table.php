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
        Schema::create('spk_training_data', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_application_id')->nullable()->constrained('student_applications')->cascadeOnDelete();
            $table->foreignId('source_encoding_id')->nullable()->constrained('application_feature_encodings')->cascadeOnDelete();
            $table->unsignedInteger('schema_version')->default(1);
            $table->unsignedInteger('encoding_version')->default(1);

            foreach ($this->encodedFeatureColumns() as $column) {
                $table->unsignedTinyInteger($column);
            }

            $table->string('label', 20);
            $table->unsignedTinyInteger('label_class');
            $table->string('decision_status', 20)->nullable();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('admin_corrected')->default(false);
            $table->text('correction_note')->nullable();
            $table->timestamps();

            $table->unique(['source_encoding_id'], 'spk_training_data_source_encoding_unique');
            $table->index(['schema_version', 'label_class']);
            $table->index(['is_active', 'updated_at']);
            $table->index(['source_application_id', 'schema_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_training_data');
    }
};
