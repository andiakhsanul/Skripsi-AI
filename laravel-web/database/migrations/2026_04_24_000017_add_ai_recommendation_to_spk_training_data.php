<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_training_data', function (Blueprint $table): void {
            $table->string('ai_recommendation', 20)->nullable()->after('label_class')
                ->comment('Hasil rekomendasi final AI sebelum admin memutuskan (Layak / Indikasi)');
            $table->string('ai_catboost_label', 20)->nullable()->after('ai_recommendation')
                ->comment('Label CatBoost saat prediksi awal');
            $table->string('ai_naive_bayes_label', 20)->nullable()->after('ai_catboost_label')
                ->comment('Label Naive Bayes saat prediksi awal');
            $table->float('ai_catboost_confidence')->nullable()->after('ai_naive_bayes_label')
                ->comment('Confidence CatBoost saat prediksi awal');
            $table->float('ai_naive_bayes_confidence')->nullable()->after('ai_catboost_confidence')
                ->comment('Confidence Naive Bayes saat prediksi awal');
        });
    }

    public function down(): void
    {
        Schema::table('spk_training_data', function (Blueprint $table): void {
            $table->dropColumn([
                'ai_recommendation',
                'ai_catboost_label',
                'ai_naive_bayes_label',
                'ai_catboost_confidence',
                'ai_naive_bayes_confidence',
            ]);
        });
    }
};
