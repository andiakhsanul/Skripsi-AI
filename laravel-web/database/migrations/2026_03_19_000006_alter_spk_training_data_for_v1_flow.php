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
        if (! Schema::hasTable('spk_training_data')) {
            return;
        }

        Schema::table('spk_training_data', function (Blueprint $table) {
            if (! Schema::hasColumn('spk_training_data', 'schema_version')) {
                $table->unsignedInteger('schema_version')->default(1)->after('label');
                $table->index('schema_version');
            }

            if (! Schema::hasColumn('spk_training_data', 'source_application_id')) {
                $table->unsignedBigInteger('source_application_id')->nullable()->after('schema_version');
                $table->unique('source_application_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('spk_training_data')) {
            return;
        }

        Schema::table('spk_training_data', function (Blueprint $table) {
            if (Schema::hasColumn('spk_training_data', 'source_application_id')) {
                $table->dropUnique(['source_application_id']);
                $table->dropColumn('source_application_id');
            }

            if (Schema::hasColumn('spk_training_data', 'schema_version')) {
                $table->dropIndex(['schema_version']);
                $table->dropColumn('schema_version');
            }
        });
    }
};
