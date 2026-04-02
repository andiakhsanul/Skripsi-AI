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
        Schema::create('spk_training_data', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_application_id')->nullable()->unique();
            $table->unsignedInteger('schema_version')->default(1);

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

            $table->string('label', 20);
            $table->unsignedTinyInteger('label_class');
            $table->boolean('is_active')->default(true);
            $table->boolean('admin_corrected')->default(false);
            $table->text('correction_note')->nullable();
            $table->timestamps();

            $table->index(['schema_version', 'label_class']);
            $table->index(['is_active', 'updated_at']);
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
