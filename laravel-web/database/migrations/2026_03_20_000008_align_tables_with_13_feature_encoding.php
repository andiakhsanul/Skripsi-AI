<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STUDENT_TABLE = 'student_applications';

    private const TRAINING_TABLE = 'spk_training_data';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->alignStudentApplicationsTable();
        $this->alignTrainingTable();
        $this->normalizeExistingValues();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Migration ini fokus penyesuaian skema existing.
        // Rollback penuh tidak dilakukan agar data historis tetap aman.
    }

    private function alignStudentApplicationsTable(): void
    {
        $this->addMissingTinyIntegerColumns(self::STUDENT_TABLE, [
            ['name' => 'kip', 'default' => 0, 'after' => 'schema_version'],
            ['name' => 'pkh', 'default' => 0, 'after' => 'kip'],
            ['name' => 'kks', 'default' => 0, 'after' => 'pkh'],
            ['name' => 'dtks', 'default' => 0, 'after' => 'kks'],
            ['name' => 'sktm', 'default' => 0, 'after' => 'dtks'],
            ['name' => 'penghasilan_ayah', 'default' => 3, 'after' => 'penghasilan_gabungan'],
            ['name' => 'penghasilan_ibu', 'default' => 3, 'after' => 'penghasilan_ayah'],
            ['name' => 'jumlah_tanggungan', 'default' => 3, 'after' => 'penghasilan_ibu'],
            ['name' => 'anak_ke', 'default' => 3, 'after' => 'jumlah_tanggungan'],
            ['name' => 'status_orangtua', 'default' => 3, 'after' => 'anak_ke'],
            ['name' => 'status_rumah', 'default' => 3, 'after' => 'status_orangtua'],
        ]);
    }

    private function alignTrainingTable(): void
    {
        $this->addMissingTinyIntegerColumns(self::TRAINING_TABLE, [
            ['name' => 'kip', 'default' => 0, 'after' => 'kip_sma'],
            ['name' => 'pkh', 'default' => 0, 'after' => 'kip'],
            ['name' => 'kks', 'default' => 0, 'after' => 'pkh'],
            ['name' => 'dtks', 'default' => 0, 'after' => 'kks'],
            ['name' => 'sktm', 'default' => 0, 'after' => 'dtks'],
            ['name' => 'penghasilan_ayah', 'default' => 3, 'after' => 'penghasilan_gabungan'],
            ['name' => 'penghasilan_ibu', 'default' => 3, 'after' => 'penghasilan_ayah'],
            ['name' => 'jumlah_tanggungan', 'default' => 3, 'after' => 'penghasilan_ibu'],
            ['name' => 'anak_ke', 'default' => 3, 'after' => 'jumlah_tanggungan'],
            ['name' => 'status_orangtua', 'default' => 3, 'after' => 'anak_ke'],
            ['name' => 'status_rumah', 'default' => 3, 'after' => 'status_orangtua'],
        ]);

        if (! Schema::hasTable(self::TRAINING_TABLE) || Schema::hasColumn(self::TRAINING_TABLE, 'label_class')) {
            return;
        }

        Schema::table(self::TRAINING_TABLE, function (Blueprint $table): void {
            $table->unsignedTinyInteger('label_class')->nullable()->after('label');
            $table->index('label_class');
        });
    }

    /**
     * @param array<int, array{name: string, default: int, after?: string}> $definitions
     */
    private function addMissingTinyIntegerColumns(string $tableName, array $definitions): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $definitions): void {
            foreach ($definitions as $definition) {
                $columnName = $definition['name'];
                if (Schema::hasColumn($tableName, $columnName)) {
                    continue;
                }

                $column = $table->unsignedTinyInteger($columnName)->default((int) $definition['default']);

                if (isset($definition['after']) && $definition['after'] !== '') {
                    $column->after($definition['after']);
                }
            }
        });
    }

    private function normalizeExistingValues(): void
    {
        $this->normalizeIncomeColumn(self::STUDENT_TABLE, 'penghasilan_gabungan');
        $this->normalizePowerColumn(self::STUDENT_TABLE, 'daya_listrik');

        if (! Schema::hasTable(self::TRAINING_TABLE)) {
            return;
        }

        $this->backfillKipFromLegacyColumn();
        $this->normalizeLegacyLabelValues();
        $this->backfillLabelClass();

        $this->normalizeIncomeColumn(self::TRAINING_TABLE, 'penghasilan_gabungan');
        $this->normalizePowerColumn(self::TRAINING_TABLE, 'daya_listrik');
    }

    private function backfillKipFromLegacyColumn(): void
    {
        if (! Schema::hasColumn(self::TRAINING_TABLE, 'kip_sma') || ! Schema::hasColumn(self::TRAINING_TABLE, 'kip')) {
            return;
        }

        DB::statement(
            "UPDATE spk_training_data SET kip = COALESCE(NULLIF(kip, 0), kip_sma, 0)"
        );
    }

    private function normalizeLegacyLabelValues(): void
    {
        if (! Schema::hasColumn(self::TRAINING_TABLE, 'label')) {
            return;
        }

        DB::statement(
            "UPDATE spk_training_data SET label = 'Indikasi' WHERE LOWER(label) = LOWER('Tidak Layak')"
        );
    }

    private function backfillLabelClass(): void
    {
        if (! Schema::hasColumn(self::TRAINING_TABLE, 'label_class')) {
            return;
        }

        DB::statement(
            "UPDATE spk_training_data\n"
            . "SET label_class = CASE\n"
            . "    WHEN LOWER(label) = LOWER('Indikasi') THEN 1\n"
            . "    ELSE 0\n"
            . "END\n"
            . "WHERE label_class IS NULL"
        );
    }

    private function normalizeIncomeColumn(string $tableName, string $columnName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        DB::statement(sprintf(
            "UPDATE %s\n"
            . "SET %s = CASE\n"
            . "    WHEN %s < 1000000 THEN 1\n"
            . "    WHEN %s < 4000000 THEN 2\n"
            . "    ELSE 3\n"
            . "END\n"
            . "WHERE %s > 3",
            $tableName,
            $columnName,
            $columnName,
            $columnName,
            $columnName
        ));
    }

    private function normalizePowerColumn(string $tableName, string $columnName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        DB::statement(sprintf(
            "UPDATE %s\n"
            . "SET %s = CASE\n"
            . "    WHEN %s <= 0 THEN 1\n"
            . "    WHEN %s <= 900 THEN 2\n"
            . "    ELSE 3\n"
            . "END\n"
            . "WHERE %s > 3 OR %s <= 0",
            $tableName,
            $columnName,
            $columnName,
            $columnName,
            $columnName,
            $columnName
        ));
    }
};
