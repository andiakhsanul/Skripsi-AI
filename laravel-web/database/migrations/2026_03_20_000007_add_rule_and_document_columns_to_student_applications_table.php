<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'student_applications';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $columns = [
            'rule_score' => static fn (Blueprint $table) => $table
                ->decimal('rule_score', 8, 4)
                ->nullable()
                ->after('review_priority'),
            'rule_recommendation' => static fn (Blueprint $table) => $table
                ->string('rule_recommendation', 30)
                ->nullable()
                ->after('rule_score'),
            'document_submission_link' => static fn (Blueprint $table) => $table
                ->string('document_submission_link', 2048)
                ->nullable()
                ->after('rule_recommendation'),
            'supporting_document_url' => static fn (Blueprint $table) => $table
                ->string('supporting_document_url', 2048)
                ->nullable()
                ->after('document_submission_link'),
            'supporting_document_path' => static fn (Blueprint $table) => $table
                ->string('supporting_document_path', 512)
                ->nullable()
                ->after('supporting_document_url'),
        ];

        Schema::table(self::TABLE, function (Blueprint $table) use ($columns): void {
            foreach ($columns as $columnName => $definition) {
                if (Schema::hasColumn(self::TABLE, $columnName)) {
                    continue;
                }

                $definition($table);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $droppableColumns = [
            'supporting_document_path',
            'supporting_document_url',
            'document_submission_link',
            'rule_recommendation',
            'rule_score',
        ];

        $existingColumns = array_values(array_filter(
            $droppableColumns,
            static fn (string $column): bool => Schema::hasColumn(self::TABLE, $column)
        ));

        if ($existingColumns === []) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($existingColumns): void {
            $table->dropColumn($existingColumns);
        });
    }
};
