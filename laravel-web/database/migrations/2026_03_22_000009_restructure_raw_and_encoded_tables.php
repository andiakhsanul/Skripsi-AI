<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Menambahkan kolom RAW ke student_applications dan
 * kolom admin_corrected ke spk_training_data.
 *
 * Arsitektur 2-tabel:
 *  - student_applications  → menyimpan input ASLI dari mahasiswa (Rupiah, string, dll)
 *  - spk_training_data     → menyimpan nilai ter-ENCODE (0/1 dan 1/2/3) untuk ML model
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Tabel 1: student_applications (input RAW) ────────────────────
        Schema::table('student_applications', function (Blueprint $table): void {
            // Kolom RAW penghasilan (nilai Rupiah asli dari mahasiswa)
            if (! Schema::hasColumn('student_applications', 'penghasilan_gabungan_raw')) {
                $table->unsignedBigInteger('penghasilan_gabungan_raw')->nullable()->after('schema_version')
                    ->comment('Penghasilan gabungan dalam Rupiah — nilai asli sebelum encoding');
            }
            if (! Schema::hasColumn('student_applications', 'penghasilan_ayah_raw')) {
                $table->unsignedBigInteger('penghasilan_ayah_raw')->nullable()->after('penghasilan_gabungan_raw')
                    ->comment('Penghasilan ayah dalam Rupiah — nilai asli sebelum encoding');
            }
            if (! Schema::hasColumn('student_applications', 'penghasilan_ibu_raw')) {
                $table->unsignedBigInteger('penghasilan_ibu_raw')->nullable()->after('penghasilan_ayah_raw')
                    ->comment('Penghasilan ibu dalam Rupiah — nilai asli sebelum encoding');
            }

            // Kolom RAW tanggungan & urutan anak
            if (! Schema::hasColumn('student_applications', 'jumlah_tanggungan_raw')) {
                $table->unsignedTinyInteger('jumlah_tanggungan_raw')->nullable()->after('penghasilan_ibu_raw')
                    ->comment('Jumlah tanggungan keluarga (orang) — nilai asli');
            }
            if (! Schema::hasColumn('student_applications', 'anak_ke_raw')) {
                $table->unsignedTinyInteger('anak_ke_raw')->nullable()->after('jumlah_tanggungan_raw')
                    ->comment('Urutan anak — nilai asli (mis. anak ke-3 = 3)');
            }

            // Kolom RAW status string
            if (! Schema::hasColumn('student_applications', 'status_orangtua_raw')) {
                $table->string('status_orangtua_raw', 50)->nullable()->after('anak_ke_raw')
                    ->comment('Status orang tua: Lengkap / Yatim / Piatu / Yatim Piatu');
            }
            if (! Schema::hasColumn('student_applications', 'status_rumah_raw')) {
                $table->string('status_rumah_raw', 50)->nullable()->after('status_orangtua_raw')
                    ->comment('Status rumah: Milik Sendiri / Sewa / Menumpang / Tidak Punya');
            }
            if (! Schema::hasColumn('student_applications', 'daya_listrik_raw')) {
                $table->string('daya_listrik_raw', 50)->nullable()->after('status_rumah_raw')
                    ->comment('Daya listrik: PLN >900VA / PLN 450-900VA / Non-PLN');
            }

            // Kolom PDF Ditmawa (formulir offline dari Ditmawa)
            if (! Schema::hasColumn('student_applications', 'ditmawa_pdf_path')) {
                $table->string('ditmawa_pdf_path')->nullable()->after('parameters_extra')
                    ->comment('Path file PDF Ditmawa yang diupload mahasiswa');
            }
            if (! Schema::hasColumn('student_applications', 'ditmawa_pdf_uploaded_at')) {
                $table->timestamp('ditmawa_pdf_uploaded_at')->nullable()->after('ditmawa_pdf_path')
                    ->comment('Waktu upload PDF Ditmawa');
            }

            // Rule scoring (jika belum ada dari migration sebelumnya)
            if (! Schema::hasColumn('student_applications', 'rule_score')) {
                $table->decimal('rule_score', 8, 4)->nullable()->after('review_priority');
            }
            if (! Schema::hasColumn('student_applications', 'rule_recommendation')) {
                $table->string('rule_recommendation', 30)->nullable()->after('rule_score');
            }
        });

        // ─── Tabel 2: spk_training_data (data ter-encode) ──────────────────
        Schema::table('spk_training_data', function (Blueprint $table): void {
            if (! Schema::hasColumn('spk_training_data', 'admin_corrected')) {
                $table->boolean('admin_corrected')->default(false)->after('is_active')
                    ->comment('True jika admin sudah mengoreksi nilai encoding secara manual');
            }
            if (! Schema::hasColumn('spk_training_data', 'correction_note')) {
                $table->text('correction_note')->nullable()->after('admin_corrected')
                    ->comment('Catatan admin saat melakukan koreksi encoding');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table): void {
            $cols = [
                'penghasilan_gabungan_raw', 'penghasilan_ayah_raw', 'penghasilan_ibu_raw',
                'jumlah_tanggungan_raw', 'anak_ke_raw',
                'status_orangtua_raw', 'status_rumah_raw', 'daya_listrik_raw',
                'ditmawa_pdf_path', 'ditmawa_pdf_uploaded_at',
                'rule_score', 'rule_recommendation',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('student_applications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('spk_training_data', function (Blueprint $table): void {
            if (Schema::hasColumn('spk_training_data', 'admin_corrected')) {
                $table->dropColumn('admin_corrected');
            }
            if (Schema::hasColumn('spk_training_data', 'correction_note')) {
                $table->dropColumn('correction_note');
            }
        });
    }
};
