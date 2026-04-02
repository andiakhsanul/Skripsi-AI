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
        Schema::create('student_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
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

            $table->string('submitted_pdf_path', 512);
            $table->string('submitted_pdf_original_name', 255);
            $table->timestamp('submitted_pdf_uploaded_at');

            $table->string('status', 20)->default('Submitted');
            $table->string('admin_decision', 20)->nullable();
            $table->foreignId('admin_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_decision_note')->nullable();
            $table->timestamp('admin_decided_at')->nullable();
            $table->timestamps();

            $table->index(['student_user_id', 'created_at']);
            $table->index(['status', 'created_at']);
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
