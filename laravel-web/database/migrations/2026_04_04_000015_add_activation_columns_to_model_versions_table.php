<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_versions', function (Blueprint $table): void {
            $table->boolean('is_current')->default(false)->after('status');
            $table->timestamp('activated_at')->nullable()->after('trained_at');

            $table->index(['is_current', 'status']);
            $table->index(['activated_at', 'status']);
        });

        $latestReady = DB::table('model_versions')
            ->where('status', 'ready')
            ->orderByRaw('COALESCE(trained_at, created_at) DESC')
            ->orderByDesc('id')
            ->first(['id', 'trained_at', 'created_at']);

        if ($latestReady) {
            DB::table('model_versions')
                ->where('id', $latestReady->id)
                ->update([
                    'is_current' => true,
                    'activated_at' => $latestReady->trained_at ?? $latestReady->created_at,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('model_versions', function (Blueprint $table): void {
            $table->dropIndex(['is_current', 'status']);
            $table->dropIndex(['activated_at', 'status']);
            $table->dropColumn(['is_current', 'activated_at']);
        });
    }
};
