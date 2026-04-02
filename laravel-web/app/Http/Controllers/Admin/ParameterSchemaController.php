<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParameterSchemaVersion;
use App\Services\SpreadsheetParameterImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ParameterSchemaController extends Controller
{
    public function __construct(
        private readonly SpreadsheetParameterImporter $importer,
    ) {}

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $validated['file'];

        try {
            $definitions = $this->importer->parse(
                $file->getRealPath(),
                $file->getClientOriginalName()
            );
        } catch (RuntimeException $runtimeException) {
            return response()->json([
                'status' => 'error',
                'message' => $runtimeException->getMessage(),
            ], 422);
        }

        $schema = DB::transaction(function () use ($definitions, $file, $request) {
            ParameterSchemaVersion::query()->where('is_active', true)->update(['is_active' => false]);
            $nextVersion = (int) (ParameterSchemaVersion::query()->max('version') ?? 0) + 1;

            return ParameterSchemaVersion::query()->create([
                'version' => $nextVersion,
                'source_file_name' => $file->getClientOriginalName(),
                'parameter_definitions' => $definitions,
                'is_active' => true,
                'imported_by' => $request->user()->id,
            ]);
        });

        $coreCount = collect($schema->parameter_definitions)
            ->filter(static fn ($item) => (bool) ($item['is_core'] ?? false))
            ->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Schema parameter berhasil diimpor',
            'data' => [
                'schema_version' => $schema->version,
                'parameter_count' => count($schema->parameter_definitions ?? []),
                'core_parameter_count' => $coreCount,
                'source_file_name' => $schema->source_file_name,
            ],
        ], 201);
    }

    public function versions(): JsonResponse
    {
        $schemas = ParameterSchemaVersion::query()
            ->orderByDesc('version')
            ->get()
            ->map(static function (ParameterSchemaVersion $schema): array {
                $definitions = $schema->parameter_definitions ?? [];
                $coreCount = collect($definitions)
                    ->filter(static fn ($item) => (bool) ($item['is_core'] ?? false))
                    ->count();

                return [
                    'version' => $schema->version,
                    'is_active' => $schema->is_active,
                    'source_file_name' => $schema->source_file_name,
                    'parameter_count' => count($definitions),
                    'core_parameter_count' => $coreCount,
                    'created_at' => $schema->created_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $schemas,
        ]);
    }
}
