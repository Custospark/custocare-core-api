<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryItem\InventoryItemImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InventoryItemImportController extends Controller
{
    public function __construct(
        protected InventoryItemImportService $importService,
    ) {}

    public function downloadTemplate()
    {
        $spreadsheet = $this->importService->generateTemplate();
        $writer = new Xlsx($spreadsheet);

        $fileName = 'inventory-item-import-template.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $facilityId = $request->header('X-Facility-Id') ?? $request->header('X-Active-Facility-Id');
        if (!$facilityId) {
            return response()->json([
                'success' => false,
                'message' => 'Facility ID is required in request headers (X-Facility-Id).',
            ], 400);
        }

        set_time_limit(600);
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', '600');
            @ini_set('memory_limit', '512M');
        }

        $results = $this->importService->import(
            (int) $facilityId,
            $request->file('file')->getPathname(),
            $request->user()?->id,
        );

        return response()->json($results);
    }
}
