<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('parameter_schema_versions');
    }

    public function down(): void
    {
        // Tabel ini dihapus permanen — tidak perlu di-recreate.
    }
};
