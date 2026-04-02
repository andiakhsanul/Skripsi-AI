<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('application_model_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('student_applications')->cascadeOnDelete();
            $table->unsignedInteger('schema_version');

            $table->unsignedTinyInteger('kip');
            $table->unsignedTinyInteger('pkh');
            $table->unsignedTinyInteger('kks');
            $table->unsignedTinyInteger('dtks');
            $table->unsignedTinyInteger('sktm');
            $table->unsignedTinyInteger('penghasilan_gabungan');
            $table->unsignedTinyInteger('penghasilan_ayah');
            $table->unsignedTinyInteger('penghasilan_ibu');
            $table->unsignedTinyInteger('jumlah_tanggungan');
            $table->unsignedTinyInteger('anak_ke');
            $table->unsignedTinyInteger('status_orangtua');
            $table->unsignedTinyInteger('status_rumah');
            $table->unsignedTinyInteger('daya_listrik');

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

            $table->index(['schema_version', 'review_priority']);
            $table->index(['model_ready', 'final_recommendation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_model_snapshots');
    }
};
