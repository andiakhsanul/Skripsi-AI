<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function socialSupportColumns(): array
    {
        return ['kip', 'pkh', 'kks', 'dtks', 'sktm'];
    }

    public function up(): void
    {
        Schema::create('student_applications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('schema_version')->default(1);
            $table->foreignId('student_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submission_source', 40)->default('online_student');

            $table->string('applicant_name')->nullable();
            $table->string('applicant_email')->nullable();
            $table->string('study_program')->nullable();
            $table->string('faculty')->nullable();

            $table->string('source_reference_number', 100)->nullable();
            $table->string('source_document_link', 1024)->nullable();
            $table->string('source_sheet_name', 150)->nullable();
            $table->unsignedInteger('source_row_number')->nullable();
            $table->string('source_label_text', 255)->nullable();
            $table->timestamp('imported_at')->nullable();

            foreach ($this->socialSupportColumns() as $column) {
                $table->unsignedTinyInteger($column);
            }

            $table->unsignedBigInteger('penghasilan_ayah_rupiah')->nullable();
            $table->unsignedBigInteger('penghasilan_ibu_rupiah')->nullable();
            $table->unsignedBigInteger('penghasilan_gabungan_rupiah')->nullable();
            $table->unsignedSmallInteger('jumlah_tanggungan_raw')->nullable();
            $table->unsignedSmallInteger('anak_ke_raw')->nullable();
            $table->string('status_orangtua_text', 255)->nullable();
            $table->string('status_rumah_text', 255)->nullable();
            $table->string('daya_listrik_text', 255)->nullable();

            $table->string('submitted_pdf_path', 512)->nullable();
            $table->string('submitted_pdf_original_name', 255)->nullable();
            $table->timestamp('submitted_pdf_uploaded_at')->nullable();

            $table->string('status', 20)->default('Submitted');
            $table->string('admin_decision', 20)->nullable();
            $table->foreignId('admin_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_decision_note')->nullable();
            $table->timestamp('admin_decided_at')->nullable();
            $table->timestamps();

            $table->index(['student_user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['schema_version', 'status']);
            $table->index(['submission_source', 'status']);
            $table->index(['source_sheet_name', 'source_row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_applications');
    }
};
