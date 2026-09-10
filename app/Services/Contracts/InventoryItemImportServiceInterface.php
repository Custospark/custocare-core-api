<?php

namespace App\Services\Contracts;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

interface InventoryItemImportServiceInterface
{
    public function generateTemplate(): Spreadsheet;

    /**
     * @return array{imported: int, skipped: int, total_rows: int, errors: array, generated_codes: int}
     */
    public function import(int $facilityId, string $filePath, ?int $actorUserId = null): array;
}
