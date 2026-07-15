<?php

namespace App\Services\InventoryItem;

use App\Models\InventoryItem;
use App\Models\Staff;
use App\Services\Contracts\InventoryLedgerServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InventoryItemImportService
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

        $results = ['imported' => 0, 'errors' => [], 'total_rows' => count($rowEntries)];
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
                    $data = $this->mapRow($entry['row']);
                    $codeKey = $data['item_code'] ? strtolower(trim($data['item_code'])) : null;

                    $validator = Validator::make($data, $this->validationRules($facilityId));

                    if ($codeKey) {
                        if (isset($existingCodes[$codeKey]) || isset($importCodes[$codeKey])) {
                            $validator->after(function ($v) {
                                $v->errors()->add('item_code', 'The item code has already been taken.');
                            });
                        }
                    }

                    if ($validator->fails()) {
                        $results['errors'][] = ['row' => $rowNum, 'errors' => $validator->errors()->toArray()];
                        continue;
                    }

                    $data['facility_id'] = $facilityId;
                    $data['item_uuid'] = (string) Str::uuid();
                    $data['created_by_staff_id'] = $staffId;
                    if (!isset($data['package_quantity']) || $data['package_quantity'] < 1) {
                        $data['package_quantity'] = 1;
                    }
                    if (!isset($data['currency_code'])) {
                        $data['currency_code'] = 'UGX';
                    }

                    $data = $this->setBooleans($data);
                    unset($data['stock_quantity']);

                    $item = InventoryItem::create($data);

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

                    if ($codeKey) {
                        $importCodes[$codeKey] = true;
                    }

                    $results['imported']++;
                }
            });
        }

        return $results;
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

        return [
            'item_name' => $get(0),
            'item_code' => $get(1),
            'item_category' => $this->normalizeCategory($get(2)),
            'unit_of_measure' => $get(3),
            'package_quantity' => $get(4) !== null ? (int) $get(4) : 1,
            'unit_cost' => $get(5),
            'currency_code' => $get(6) ? strtoupper($get(6)) : 'UGX',
            'generic_name' => $get(7),
            'brand_name' => $get(8),
            'ndc_code' => $get(9),
            'dosage_form' => $this->normalizeDosageForm($get(10)),
            'strength' => $get(11),
            'route_of_administration' => $this->normalizeRoute($get(12)),
            'manufacturer' => $get(13),
            'supplier' => $get(14),
            'reorder_point' => $get(15) !== null ? (int) $get(15) : null,
            'reorder_quantity' => $get(16) !== null ? (int) $get(16) : null,
            'safety_stock_level' => $get(17) !== null ? (int) $get(17) : null,
            'max_stock_level' => $get(18) !== null ? (int) $get(18) : null,
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
