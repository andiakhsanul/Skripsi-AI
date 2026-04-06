<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_versions', function (Blueprint $table): void {
            $table->json('catboost_metrics')->nullable()->after('naive_bayes_validation_accuracy');
            $table->json('naive_bayes_metrics')->nullable()->after('catboost_metrics');
        });
    }

    public function down(): void
    {
        Schema::table('model_versions', function (Blueprint $table): void {
            $table->dropColumn([
                'catboost_metrics',
                'naive_bayes_metrics',
            ]);
        });
    }
};
