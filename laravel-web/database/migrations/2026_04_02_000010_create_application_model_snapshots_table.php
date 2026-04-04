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
        Schema::create('application_model_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('student_applications')->cascadeOnDelete();
            $table->foreignId('encoding_id')->constrained('application_feature_encodings')->cascadeOnDelete();
            $table->unsignedInteger('schema_version');
            $table->unsignedBigInteger('model_version_id')->nullable();

            foreach ($this->encodedFeatureColumns() as $column) {
                $table->unsignedTinyInteger($column);
            }

            $table->boolean('model_ready')->default(false);
            $table->string('catboost_label', 20)->nullable();
            $table->decimal('catboost_confidence', 6, 4)->nullable();
            $table->string('naive_bayes_label', 20)->nullable();
            $table->decimal('naive_bayes_confidence', 6, 4)->nullable();
            $table->boolean('disagreement_flag')->default(false);
            $table->string('final_recommendation', 20)->nullable();
            $table->string('review_priority', 20)->default('normal');
            $table->decimal('rule_score', 8, 4)->nullable();
            $table->string('rule_recommendation', 20)->nullable();
            $table->timestamp('snapshotted_at')->nullable();
            $table->timestamps();

            $table->index(['encoding_id', 'snapshotted_at']);
            $table->index(['schema_version', 'review_priority']);
            $table->index(['model_ready', 'final_recommendation']);
            $table->index(['model_version_id', 'snapshotted_at'], 'app_model_snapshots_model_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_model_snapshots');
    }
};
