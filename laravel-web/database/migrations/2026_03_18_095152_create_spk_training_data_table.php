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
        if (Schema::hasTable('spk_training_data')) {
            return;
        }

        Schema::create('spk_training_data', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('kip_sma')->nullable()->comment('Legacy column');
            $table->unsignedTinyInteger('kip')->default(0);
            $table->unsignedTinyInteger('pkh')->default(0);
            $table->unsignedTinyInteger('kks')->default(0);
            $table->unsignedTinyInteger('dtks')->default(0);
            $table->unsignedTinyInteger('sktm')->default(0);
            $table->unsignedTinyInteger('penghasilan_gabungan');
            $table->unsignedTinyInteger('penghasilan_ayah')->default(3);
            $table->unsignedTinyInteger('penghasilan_ibu')->default(3);
            $table->unsignedTinyInteger('jumlah_tanggungan')->default(3);
            $table->unsignedTinyInteger('anak_ke')->default(3);
            $table->unsignedTinyInteger('status_orangtua')->default(3);
            $table->unsignedTinyInteger('status_rumah')->default(3);
            $table->unsignedTinyInteger('daya_listrik');
            $table->string('label', 50)->comment('Layak or Indikasi');
            $table->unsignedTinyInteger('label_class')->nullable();
            $table->unsignedInteger('schema_version')->default(1);
            $table->unsignedBigInteger('source_application_id')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spk_training_data');
    }
};
