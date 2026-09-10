<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryItem\ImportInventoryItemsRequest;
use App\Services\Contracts\InventoryItemImportServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InventoryItemImportController extends Controller
{
    public function __construct(
        protected InventoryItemImportServiceInterface $importService,
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

    public function import(ImportInventoryItemsRequest $request): JsonResponse
    {
        $facilityId = $request->header('X-Facility-Id') ?? $request->header('X-Active-Facility-Id');
        if (!$facilityId) {
            return response()->json([
                'success' => false,
                'message' => 'Facility ID is required in request headers (X-Facility-Id).',
            ], 400);
        }

        // Longer timeouts for large imports (1,000+ rows): 15 min + more memory.
        // Files are still processed synchronously in 100-row chunks.
        set_time_limit(900);
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', '900');
            @ini_set('memory_limit', '768M');
        }

        try {
            $results = $this->importService->import(
                (int) $facilityId,
                $request->file('file')->getPathname(),
                $request->user()?->id,
            );
        } catch (\Throwable $e) {
            Log::error('Inventory import failed', [
                'facility_id' => $facilityId,
                'user_id' => $request->user()?->id,
                'filename' => $request->file('file')?->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'We could not complete the import. Please try again or use a smaller file.',
                'imported' => 0,
                'skipped' => 0,
                'total_rows' => 0,
                'errors' => [],
            ], 500);
        }

        $imported = (int) ($results['imported'] ?? 0);
        $total = (int) ($results['total_rows'] ?? 0);
        $errorCount = is_array($results['errors'] ?? null) ? count($results['errors']) : 0;

        if ($total > 0 && $errorCount === 0) {
            $message = "Imported {$imported} of {$total} items successfully.";
            $status = 200;
        } elseif ($imported > 0) {
            $message = "Imported {$imported} of {$total} items. {$errorCount} row(s) need attention.";
            $status = 207;
        } elseif ($total === 0) {
            $message = 'No data rows found in the uploaded file.';
            $status = 422;
        } else {
            $message = 'No items were imported. Please review the row errors below.';
            $status = 422;
        }

        return response()->json([
            'success' => $errorCount === 0 && $imported > 0,
            'message' => $message,
            ...$results,
        ], $status);
    }
}
