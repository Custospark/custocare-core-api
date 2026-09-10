<?php

namespace App\Services\InventoryItem;

use App\Models\InventoryItem;
use App\Models\Staff;
use App\Services\Contracts\InventoryItemImportServiceInterface;
use App\Services\Contracts\InventoryLedgerServiceInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InventoryItemImportService implements InventoryItemImportServiceInterface
{
    protected const CHUNK_SIZE = 100;

    protected const HEADERS = [
        'Item Name*',
        'Item Code',
        'Category*',
        'Unit of Measure*',
        'Package Qty*',
        'Unit Cost',
        'Currency Code*',
        'Generic Name',
        'Brand Name',
        'NDC Code',
        'Dosage Form',
        'Strength',
        'Route of Administration',
        'Manufacturer',
        'Supplier',
        'Reorder Point',
        'Reorder Qty',
        'Safety Stock',
        'Max Stock Level',
        'Requires Prescription (Yes/No)',
        'Requires Refrigeration (Yes/No)',
        'Is Hazardous (Yes/No)',
        'Is Billable (Yes/No)',
        'Description',
        'Status*',
    ];

    protected const CATEGORIES = [
        'medication', 'medical_supply', 'surgical_instrument', 'diagnostic_equipment',
        'implantable_device', 'prosthetic', 'laboratory_reagent',
        'personal_protective_equipment', 'administrative_supply', 'other',
    ];

    protected const DOSAGE_FORMS = [
        'tablet', 'capsule', 'syrup', 'injection', 'cream', 'ointment',
        'solution', 'suspension', 'powder', 'inhaler', 'patch',
        'suppository', 'drops', 'spray', 'gel', 'lotion',
    ];

    protected const ROUTES = [
        'oral', 'intravenous', 'intramuscular', 'subcutaneous',
        'topical', 'inhalation', 'rectal', 'vaginal', 'ocular',
        'otic', 'nasal', 'transdermal',
    ];

    protected const VALID_STATUSES = ['active', 'inactive', 'discontinued', 'recalled'];

    protected const YES_NO = ['yes', 'no'];

    public function __construct(
        protected InventoryLedgerServiceInterface $ledgerService,
    ) {}

    public function generateTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventory Items');

        $bold = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $lastCol = chr(65 + count(self::HEADERS) - 1);
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray($bold);

        foreach (self::HEADERS as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . '1', $h);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $example = [
            'Paracetamol 500mg', '', 'medication', 'Each', '100', '5000', 'UGX',
            'Paracetamol', 'Panadol', '12345-6789', 'tablet', '500mg', 'oral',
            'Pharma Ltd', 'MedDist Co', '50', '100', '20', '500',
            'Yes', 'No', 'No', 'Yes', 'For pain and fever management', 'active',
        ];
        foreach ($example as $i => $val) {
            $sheet->setCellValue(chr(65 + $i) . '2', $val);
        }
        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']],
        ]);

        $sheet->setCellValue('A3', '');
        $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'E5E7EB']]],
        ]);

        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    public function import(int $facilityId, string $filePath, ?int $actorUserId = null): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $worksheet = $reader->load($filePath)->getActiveSheet();
        $rows = $worksheet->toArray();
        array_shift($rows);

        $rowEntries = [];
        foreach ($rows as $index => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }
            $rowEntries[] = ['index' => $index, 'row' => $row];
        }

        $results = [
            'imported' => 0,
            'skipped' => 0,
            'total_rows' => count($rowEntries),
            'errors' => [],
            'generated_codes' => 0,
        ];
        if ($rowEntries === []) {
            return $results;
        }

        $existingCodes = InventoryItem::query()
            ->where('facility_id', $facilityId)
            ->whereNotNull('item_code')
            ->pluck('item_code')
            ->map(fn ($c) => strtolower(trim((string) $c)))
            ->flip()
            ->all();
        $importCodes = [];

        $staffId = null;
        if ($actorUserId) {
            $staff = Staff::where('user_id', $actorUserId)->first();
            $staffId = $staff?->id;
        }

        foreach (array_chunk($rowEntries, self::CHUNK_SIZE) as $chunk) {
            DB::transaction(function () use ($facilityId, $chunk, $existingCodes, &$importCodes, &$results, $staffId) {
                foreach ($chunk as $entry) {
                    $rowNum = $entry['index'] + 2;

                    try {
                        $data = $this->mapRow($entry['row']);

                        // Null/default safety: auto-generate missing or duplicate codes
                        // so we never hit DB NOT NULL (1048) or leak raw SQLSTATE.
                        $rawCode = isset($data['item_code']) ? trim((string) $data['item_code']) : '';
                        if ($rawCode === '') {
                            $data['item_code'] = $this->generateUniqueItemCode($facilityId, $existingCodes, $importCodes);
                            $results['generated_codes']++;
                        } else {
                            $data['item_code'] = $rawCode;
                        }

                        $codeKey = strtolower(trim($data['item_code']));

                        // Pre-validation defaults: blanks become sensible values
                        // so missing optional columns don't fail validation.
                        if (empty($data['item_category'])) {
                            $data['item_category'] = 'other';
                        }
                        if (empty($data['unit_of_measure'])) {
                            $data['unit_of_measure'] = 'each';
                        }
                        if (!isset($data['package_quantity']) || (int) $data['package_quantity'] < 1) {
                            $data['package_quantity'] = 1;
                        }
                        if (empty($data['currency_code'])) {
                            $data['currency_code'] = 'UGX';
                        } else {
                            $data['currency_code'] = strtoupper(trim((string) $data['currency_code']));
                        }
                        if (empty($data['status'])) {
                            $data['status'] = 'active';
                        }

                        $validator = Validator::make($data, $this->validationRules($facilityId));

                        if (isset($existingCodes[$codeKey]) || isset($importCodes[$codeKey])) {
                            // Duplicate within facility or file: auto-generate a fresh
                            // code instead of failing the row, keep import flowing.
                            $data['item_code'] = $this->generateUniqueItemCode($facilityId, $existingCodes, $importCodes);
                            $codeKey = strtolower(trim($data['item_code']));
                            $results['generated_codes']++;
                            $validator = Validator::make($data, $this->validationRules($facilityId));
                        }

                        if ($validator->fails()) {
                            $results['errors'][] = ['row' => $rowNum, 'errors' => $validator->errors()->toArray()];
                            $results['skipped']++;
                            continue;
                        }

                        $data['facility_id'] = $facilityId;
                        $data['item_uuid'] = (string) Str::uuid();
                        $data['created_by_staff_id'] = $staffId;
                        if (!isset($data['package_quantity']) || (int) $data['package_quantity'] < 1) {
                            $data['package_quantity'] = 1;
                        }
                        if (empty($data['unit_of_measure'])) {
                            $data['unit_of_measure'] = 'each';
                        }
                        if (empty($data['currency_code'])) {
                            $data['currency_code'] = 'UGX';
                        } else {
                            $data['currency_code'] = strtoupper(trim((string) $data['currency_code']));
                        }
                        if (empty($data['status'])) {
                            $data['status'] = 'active';
                        }
                        if (empty($data['item_category'])) {
                            $data['item_category'] = 'other';
                        }

                        $data = $this->setBooleans($data);
                        unset($data['stock_quantity']);

                        try {
                            $item = InventoryItem::create($data);
                        } catch (QueryException $e) {
                            // Graceful per-row failure: never bubble raw SQLSTATE,
                            // never roll back the whole 100-row chunk for one bad row.
                            Log::warning('Inventory import row failed at DB layer', [
                                'facility_id' => $facilityId,
                                'row' => $rowNum,
                                'item_name' => $data['item_name'] ?? null,
                                'error' => $e->getMessage(),
                            ]);
                            $results['errors'][] = [
                                'row' => $rowNum,
                                'errors' => ['database' => [$this->friendlyDbMessage($e)]],
                            ];
                            $results['skipped']++;
                            continue;
                        }

                        try {
                            $this->ledgerService->recordAdjustment([
                                'facility_id' => $facilityId,
                                'inventory_item_id' => $item->id,
                                'quantity' => (float) $data['package_quantity'],
                                'unit_of_measure' => $data['unit_of_measure'],
                                'performed_by_staff_id' => $staffId,
                                'transaction_notes' => 'Initial stock from import',
                            ]);
                        } catch (\Throwable $e) {
                            Log::warning('Failed to seed initial ledger entry during import', [
                                'item_id' => $item->id,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        $importCodes[$codeKey] = true;

                        $results['imported']++;
                    } catch (\Throwable $e) {
                        // Last-resort guard: one unexpected row error never kills the import.
                        Log::error('Inventory import unexpected row error', [
                            'facility_id' => $facilityId,
                            'row' => $rowNum,
                            'error' => $e->getMessage(),
                        ]);
                        $results['errors'][] = [
                            'row' => $rowNum,
                            'errors' => ['row' => ['We could not import this row. Please check the values and try again.']],
                        ];
                        $results['skipped']++;
                        continue;
                    }
                }
            });
        }

        return $results;
    }

    protected function generateUniqueItemCode(int $facilityId, array $existingCodes, array $importCodes): string
    {
        do {
            $code = 'INVT-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $key = strtolower($code);
        } while (isset($existingCodes[$key]) || isset($importCodes[$key]) || InventoryItem::where('facility_id', $facilityId)->where('item_code', $code)->exists());

        return $code;
    }

    protected function friendlyDbMessage(QueryException $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, "Column 'item_code' cannot be null") || str_contains($message, '1048')) {
            return 'Item code was missing. Leave it blank to auto-generate, or provide a unique code.';
        }
        if (str_contains($message, 'Duplicate entry') || str_contains($message, '1062')) {
            return 'This item code is already used at your facility. Leave it blank to auto-generate a unique code.';
        }

        return 'We could not save this row due to a database error. Please check the values and try again.';
    }

    protected function validationRules(int $facilityId): array
    {
        return [
            'item_name' => ['required', 'string', 'max:255'],
            'item_code' => [
                'nullable',
                'string',
                'max:100',
                \Illuminate\Validation\Rule::unique('inventory_items', 'item_code')
                    ->where(fn ($q) => $q->where('facility_id', $facilityId)),
            ],
            'item_category' => ['required', 'string', 'in:' . implode(',', self::CATEGORIES)],
            'unit_of_measure' => ['required', 'string', 'max:50'],
            'package_quantity' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'currency_code' => ['nullable', 'string', 'size:3', 'alpha'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'ndc_code' => ['nullable', 'string', 'max:20'],
            'dosage_form' => ['nullable', 'string', 'in:' . implode(',', self::DOSAGE_FORMS)],
            'strength' => ['nullable', 'string', 'max:100'],
            'route_of_administration' => ['nullable', 'string', 'in:' . implode(',', self::ROUTES)],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'reorder_point' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'reorder_quantity' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'safety_stock_level' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'max_stock_level' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'requires_prescription' => ['nullable', 'string', 'in:' . implode(',', self::YES_NO)],
            'requires_refrigeration' => ['nullable', 'string', 'in:' . implode(',', self::YES_NO)],
            'is_hazardous' => ['nullable', 'string', 'in:' . implode(',', self::YES_NO)],
            'is_billable' => ['nullable', 'string', 'in:' . implode(',', self::YES_NO)],
            'item_description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:' . implode(',', self::VALID_STATUSES)],
        ];
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }

    protected function mapRow(array $row): array
    {
        $get = function (int $i) use ($row): ?string {
            $raw = $row[$i] ?? null;
            if ($raw === null || $raw === '') return null;
            $trimmed = trim((string) $raw);
            return $trimmed === '' ? null : $trimmed;
        };

        // Safe integer: non-numeric garbage becomes null (validated per-row),
        // never 0 or a DB type error. Bounds match validation max:65535.
        $safeInt = function (?string $value): ?int {
            if ($value === null) return null;
            if (!is_numeric($value)) return null;
            $int = (int) $value;
            return ($int >= 0 && $int <= 65535) ? $int : null;
        };

        // Safe money: non-numeric becomes null so the validator returns a
        // friendly per-row error instead of a DB exception.
        $rawCost = $get(5);
        $unitCost = ($rawCost !== null && is_numeric($rawCost) && (float) $rawCost >= 0)
            ? $rawCost
            : null;

        return [
            'item_name' => $get(0),
            'item_code' => $get(1),
            'item_category' => $this->normalizeCategory($get(2)),
            'unit_of_measure' => $get(3),
            'package_quantity' => $safeInt($get(4)) ?? 1,
            'unit_cost' => $unitCost,
            'currency_code' => $get(6) ? strtoupper($get(6)) : 'UGX',
            'generic_name' => $get(7),
            'brand_name' => $get(8),
            'ndc_code' => $get(9),
            'dosage_form' => $this->normalizeDosageForm($get(10)),
            'strength' => $get(11),
            'route_of_administration' => $this->normalizeRoute($get(12)),
            'manufacturer' => $get(13),
            'supplier' => $get(14),
            'reorder_point' => $safeInt($get(15)),
            'reorder_quantity' => $safeInt($get(16)),
            'safety_stock_level' => $safeInt($get(17)),
            'max_stock_level' => $safeInt($get(18)),
            'requires_prescription' => $this->normalizeYesNo($get(19)),
            'requires_refrigeration' => $this->normalizeYesNo($get(20)),
            'is_hazardous' => $this->normalizeYesNo($get(21)),
            'is_billable' => $this->normalizeYesNo($get(22)),
            'item_description' => $get(23),
            'status' => $this->normalizeStatus($get(24)),
        ];
    }

    protected function setBooleans(array $data): array
    {
        foreach (['requires_prescription', 'requires_refrigeration', 'is_hazardous', 'is_billable'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = strtolower(trim($data[$field])) === 'yes';
            } elseif (!isset($data[$field])) {
                $data[$field] = in_array($field, ['is_billable'], true);
            }
        }
        return $data;
    }

    protected function normalizeCategory(?string $value): ?string
    {
        if ($value === null) return null;
        $normalized = strtolower(str_replace([' ', '-', '_'], '_', trim($value)));
        return in_array($normalized, self::CATEGORIES, true) ? $normalized : 'other';
    }

    protected function normalizeDosageForm(?string $value): ?string
    {
        if ($value === null) return null;
        $normalized = strtolower(str_replace([' ', '-', '_'], '_', trim($value)));
        return in_array($normalized, self::DOSAGE_FORMS, true) ? $normalized : null;
    }

    protected function normalizeRoute(?string $value): ?string
    {
        if ($value === null) return null;
        $normalized = strtolower(str_replace([' ', '-', '_'], '_', trim($value)));
        return in_array($normalized, self::ROUTES, true) ? $normalized : null;
    }

    protected function normalizeStatus(?string $value): string
    {
        if ($value === null) return 'active';
        $normalized = strtolower(trim($value));
        return in_array($normalized, self::VALID_STATUSES, true) ? $normalized : 'active';
    }

    protected function normalizeYesNo(?string $value): ?string
    {
        if ($value === null) return null;
        $normalized = strtolower(trim($value));
        return in_array($normalized, self::YES_NO, true) ? $normalized : null;
    }
}
