<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_model_snapshots', function (Blueprint $table): void {
            $table->unsignedBigInteger('model_version_id')->nullable()->after('schema_version');
            $table->index(['model_version_id', 'snapshotted_at'], 'app_model_snapshots_model_version_idx');
        });
    }

    public function down(): void
    {
        Schema::table('application_model_snapshots', function (Blueprint $table): void {
            $table->dropIndex('app_model_snapshots_model_version_idx');
            $table->dropColumn('model_version_id');
        });
    }
};
