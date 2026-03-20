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
        if (!Schema::hasTable('spk_training_data')) {
            Schema::create('spk_training_data', function (Blueprint $table) {
                $table->id();
                $table->integer('kip_sma')->comment('1 for Yes, 0 for No');
                $table->decimal('penghasilan_gabungan', 14, 2);
                $table->integer('daya_listrik');
                $table->string('label', 50)->comment('Layak or Tidak Layak');
                $table->unsignedInteger('schema_version')->default(1);
                $table->unsignedBigInteger('source_application_id')->nullable()->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spk_training_data');
    }
};
