<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Partial unique index: hanya berlaku saat student_user_id NOT NULL.
        // Mencegah satu mahasiswa membuat lebih dari satu pengajuan.
        // Aplikasi offline import (student_user_id NULL) tidak terpengaruh.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS student_applications_student_user_id_unique
            ON student_applications (student_user_id)
            WHERE student_user_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS student_applications_student_user_id_unique');
    }
};
