<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('version_name')->unique();
            $table->unsignedInteger('schema_version')->default(1);
            $table->string('status', 20)->default('ready');
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('triggered_by_email')->nullable();
            $table->string('training_table', 100)->default('spk_training_data');
            $table->string('primary_model', 50)->default('catboost');
            $table->string('secondary_model', 50)->default('categorical_nb');
            $table->unsignedInteger('dataset_rows_total')->nullable();
            $table->unsignedInteger('rows_used')->nullable();
            $table->unsignedInteger('train_rows')->nullable();
            $table->unsignedInteger('validation_rows')->nullable();
            $table->string('validation_strategy', 50)->nullable();
            $table->json('class_distribution')->nullable();
            $table->string('catboost_artifact_path', 512)->nullable();
            $table->string('naive_bayes_artifact_path', 512)->nullable();
            $table->decimal('catboost_train_accuracy', 6, 4)->nullable();
            $table->decimal('catboost_validation_accuracy', 6, 4)->nullable();
            $table->decimal('naive_bayes_train_accuracy', 6, 4)->nullable();
            $table->decimal('naive_bayes_validation_accuracy', 6, 4)->nullable();
            $table->text('note')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('trained_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'trained_at']);
            $table->index(['schema_version', 'trained_at']);
            $table->index(['triggered_by_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_versions');
    }
};
