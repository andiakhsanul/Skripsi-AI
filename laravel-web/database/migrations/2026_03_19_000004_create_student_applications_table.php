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
        Schema::create('student_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('schema_version');

            // Kolom inti untuk model saat ini.
            $table->unsignedTinyInteger('kip_sma');
            $table->decimal('penghasilan_gabungan', 14, 2);
            $table->unsignedInteger('daya_listrik');

            // Parameter tambahan fleksibel per batch versi schema.
            $table->json('parameters_extra')->nullable();

            // Snapshot output model saat submit.
            $table->string('status', 20)->default('Submitted');
            $table->boolean('model_ready')->default(false);
            $table->string('catboost_label', 30)->nullable();
            $table->decimal('catboost_confidence', 6, 4)->nullable();
            $table->string('naive_bayes_label', 30)->nullable();
            $table->decimal('naive_bayes_confidence', 6, 4)->nullable();
            $table->boolean('disagreement_flag')->default(false);
            $table->string('final_recommendation', 30)->nullable();
            $table->string('review_priority', 20)->default('normal');

            // Keputusan final admin.
            $table->string('admin_decision', 20)->nullable();
            $table->foreignId('admin_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_decision_note')->nullable();
            $table->timestamp('admin_decided_at')->nullable();

            $table->timestamps();

            $table->index(['student_user_id', 'created_at']);
            $table->index(['status', 'review_priority']);
            $table->index(['schema_version', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_applications');
    }
};
