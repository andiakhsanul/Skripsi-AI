<?php

use App\Services\OfflineImport\CsvStudentApplicationImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('applications:import-kip-snbp {csv=../infra/data/processed/kip_snbp_2023_ml_dataset.csv} {--refresh}', function (): void {
    $csvArgument = (string) $this->argument('csv');
    $csvPath = str_starts_with($csvArgument, '/')
        ? $csvArgument
        : base_path($csvArgument);

    /** @var CsvStudentApplicationImporter $importer */
    $importer = app(CsvStudentApplicationImporter::class);
    $result = $importer->import($csvPath, (bool) $this->option('refresh'));

    $this->info('Import student_applications selesai.');
    $this->table(
        ['schema_version', 'deleted_existing', 'inserted', 'updated', 'total_processed'],
        [[
            $result['schema_version'],
            $result['deleted_existing'],
            $result['inserted'],
            $result['updated'],
            $result['total_processed'],
        ]]
    );
})->purpose('Import processed KIP SNBP CSV into student_applications as offline rows');
