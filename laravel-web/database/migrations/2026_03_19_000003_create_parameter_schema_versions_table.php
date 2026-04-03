<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parameter_schema_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('source_file_name');
            $table->json('parameter_definitions');
            $table->boolean('is_active')->default(true);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameter_schema_versions');
    }
};
