<?php

namespace App\Services;

use App\Common\Constants\Organization\ProductField;
use App\Common\Constants\Product\TypeVAT;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ProductExcelService
{
    public function headings(): array
    {
        return [
            'organization_id',
            'name',
            'sku',
            'unit',
            'weight',
            'cost_price',
            'sale_price',
            'barcode',
            'type',
            'length',
            'width',
            'height',
            'quantity',
            'type_vat',
            'vat_rate',
            'is_business_product',
        ];
    }

    public function templateRows(int $organizationId): array
    {
        return [
            [
                $organizationId,
                'San pham A',
                'SKU-001',
                'Hop',
                500,
                10000,
                15000,
                '8931234567890',
                ProductField::PHARMACEUTICAL->label(),
                10,
                5,
                3,
                100,
                TypeVAT::INCLUSIVE->label(),
                10,
                1,
            ],
        ];
    }

    public function getExportProducts(?int $organizationId = null): Collection
    {
        return Product::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->orderBy('id')
            ->get([
                'id',
                'organization_id',
                'name',
                'sku',
                'unit',
                'weight',
                'cost_price',
                'sale_price',
                'barcode',
                'type',
                'length',
                'width',
                'height',
                'quantity',
                'type_vat',
                'vat_rate',
                'is_business_product',
            ]);
    }

    public function exportRows(Collection $products): array
    {
        return $products
            ->map(function (Product $product): array {
                return [
                    (int) $product->organization_id,
                    (string) $product->name,
                    (string) $product->sku,
                    (string) ($product->unit ?? ''),
                    $product->weight !== null ? (float) $product->weight : '',
                    $product->cost_price !== null ? (float) $product->cost_price : '',
                    $product->sale_price !== null ? (float) $product->sale_price : '',
                    (string) ($product->barcode ?? ''),
                    $this->formatTypeForExport($product->type),
                    (string) ($product->length ?? ''),
                    (string) ($product->width ?? ''),
                    (string) ($product->height ?? ''),
                    (float) ($product->quantity ?? 0),
                    $this->formatTypeVatForExport($product->type_vat),
                    (float) ($product->vat_rate ?? 0),
                    $product->is_business_product ? 1 : 0,
                ];
            })
            ->all();
    }

    public function importFile(mixed $file, int $organizationId): int
    {
        $rows = $this->parseImportRows($file);

        DB::transaction(function () use ($rows, $organizationId): void {
            foreach ($rows as $row) {
                $existingProduct = Product::query()
                    ->where('sku', $row['sku'])
                    ->first();

                if ($existingProduct && (int) $existingProduct->organization_id !== $organizationId) {
                    throw ValidationException::withMessages([
                        'file' => [
                            __('filament.product.import_errors.sku_belongs_to_other_organization', [
                                'row' => $row['_row'],
                                'sku' => $row['sku'],
                            ]),
                        ],
                    ]);
                }

                Product::query()->updateOrCreate(
                    ['sku' => $row['sku']],
                    [
                        'organization_id' => $organizationId,
                        'name' => $row['name'],
                        'sku' => $row['sku'],
                        'unit' => $row['unit'],
                        'weight' => $row['weight'],
                        'cost_price' => $row['cost_price'],
                        'sale_price' => $row['sale_price'],
                        'barcode' => $row['barcode'],
                        'type' => $row['type'],
                        'length' => $row['length'],
                        'width' => $row['width'],
                        'height' => $row['height'],
                        'quantity' => $row['quantity'],
                        'type_vat' => $row['type_vat'],
                        'vat_rate' => $row['vat_rate'],
                        'is_business_product' => $row['is_business_product'],
                    ],
                );
            }
        });

        return count($rows);
    }

    protected function parseImportRows(mixed $file): array
    {
        $sheets = Excel::toArray(new class {
        }, $file);
        $rows = $sheets[0] ?? [];

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => [__('filament.product.import_errors.empty_file')],
            ]);
        }

        $headerRow = array_shift($rows) ?? [];
        $headerMap = $this->buildHeaderMap($headerRow);

        foreach ($this->requiredColumns() as $column) {
            if (!array_key_exists($column, $headerMap)) {
                throw ValidationException::withMessages([
                    'file' => [
                        __('filament.product.import_errors.missing_column', [
                            'column' => $this->columnLabel($column),
                        ]),
                    ],
                ]);
            }
        }

        $resolvedRows = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $rowValues = $this->extractRowValues($row, $headerMap);

            if ($this->isEmptyRow($rowValues)) {
                continue;
            }

            $resolvedRows[] = $this->normalizeRow($rowValues, $rowNumber);
        }

        if ($resolvedRows === []) {
            throw ValidationException::withMessages([
                'file' => [__('filament.product.import_errors.empty_data')],
            ]);
        }

        return $resolvedRows;
    }

    protected function buildHeaderMap(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $heading) {
            $normalizedHeading = $this->normalizeHeading((string) $heading);
            $column = $this->resolveColumnFromHeading($normalizedHeading);

            if ($column !== null) {
                $map[$column] = $index;
            }
        }

        return $map;
    }

    protected function extractRowValues(array $row, array $headerMap): array
    {
        $values = [];

        foreach ($headerMap as $column => $index) {
            $values[$column] = $row[$index] ?? null;
        }

        return $values;
    }

    protected function isEmptyRow(array $rowValues): bool
    {
        foreach ($rowValues as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function normalizeRow(array $rowValues, int $rowNumber): array
    {
        $name = $this->requireString($rowValues, 'name', $rowNumber);
        $sku = $this->requireString($rowValues, 'sku', $rowNumber);
        $unit = trim((string) ($rowValues['unit'] ?? '')) ?: 'Cai';
        $weight = $this->requireNumeric($rowValues, 'weight', $rowNumber);
        $costPrice = $this->requireNumeric($rowValues, 'cost_price', $rowNumber);
        $salePrice = $this->requireNumeric($rowValues, 'sale_price', $rowNumber);
        $barcode = trim((string) ($rowValues['barcode'] ?? ''));
        $type = $this->resolveProductType($rowValues['type'] ?? null, $rowNumber);
        $length = $this->requireNumeric($rowValues, 'length', $rowNumber);
        $width = $this->requireNumeric($rowValues, 'width', $rowNumber);
        $height = $this->requireNumeric($rowValues, 'height', $rowNumber);
        $quantity = $this->requireNumeric($rowValues, 'quantity', $rowNumber);
        $typeVat = $this->resolveTypeVat($rowValues['type_vat'] ?? null, $rowNumber);
        $vatRate = $this->requireNumeric($rowValues, 'vat_rate', $rowNumber, min: 0, max: 100);
        $isBusinessProduct = $this->resolveBoolean($rowValues['is_business_product'] ?? null);

        return [
            '_row' => $rowNumber,
            'name' => $name,
            'sku' => $sku,
            'unit' => $unit,
            'weight' => $weight,
            'cost_price' => $costPrice,
            'sale_price' => $salePrice,
            'barcode' => $barcode !== '' ? $barcode : null,
            'type' => $type,
            'length' => (string) $length,
            'width' => (string) $width,
            'height' => (string) $height,
            'quantity' => (int) $quantity,
            'type_vat' => $typeVat,
            'vat_rate' => (int) $vatRate,
            'is_business_product' => $isBusinessProduct,
        ];
    }

    protected function requireString(array $rowValues, string $column, int $rowNumber): string
    {
        $value = trim((string) ($rowValues[$column] ?? ''));

        if ($value === '') {
            throw ValidationException::withMessages([
                'file' => [
                    __('filament.product.import_errors.required_field', [
                        'row' => $rowNumber,
                        'field' => $this->columnLabel($column),
                    ]),
                ],
            ]);
        }

        return $value;
    }

    protected function requireNumeric(array $rowValues, string $column, int $rowNumber, float $min = 0, ?float $max = null): float
    {
        $rawValue = $rowValues[$column] ?? null;

        if ($rawValue === null || trim((string) $rawValue) === '') {
            throw ValidationException::withMessages([
                'file' => [
                    __('filament.product.import_errors.required_field', [
                        'row' => $rowNumber,
                        'field' => $this->columnLabel($column),
                    ]),
                ],
            ]);
        }

        $normalized = str_replace(',', '', trim((string) $rawValue));

        if (!is_numeric($normalized)) {
            throw ValidationException::withMessages([
                'file' => [
                    __('filament.product.import_errors.invalid_numeric', [
                        'row' => $rowNumber,
                        'field' => $this->columnLabel($column),
                    ]),
                ],
            ]);
        }

        $value = (float) $normalized;

        if ($value < $min || ($max !== null && $value > $max)) {
            throw ValidationException::withMessages([
                'file' => [
                    __('filament.product.import_errors.invalid_range', [
                        'row' => $rowNumber,
                        'field' => $this->columnLabel($column),
                        'min' => $min,
                        'max' => $max ?? '',
                    ]),
                ],
            ]);
        }

        return $value;
    }

    protected function resolveProductType(mixed $value, int $rowNumber): string
    {
        $normalized = $this->normalizeHeading((string) $value);

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'file' => [
                    __('filament.product.import_errors.required_field', [
                        'row' => $rowNumber,
                        'field' => $this->columnLabel('type'),
                    ]),
                ],
            ]);
        }

        if (is_numeric($value) && ProductField::tryFrom((int) $value)) {
            return (string) (int) $value;
        }

        $aliases = [
            'duoc_pham' => ProductField::PHARMACEUTICAL,
            'thuoc' => ProductField::PHARMACEUTICAL,
            'my_pham' => ProductField::COSMETICS,
            'khac' => ProductField::OTHER,
        ];

        if (array_key_exists($normalized, $aliases)) {
            return (string) $aliases[$normalized]->value;
        }

        foreach (ProductField::cases() as $case) {
            if ($this->normalizeHeading($case->label()) === $normalized) {
                return (string) $case->value;
            }
        }

        throw ValidationException::withMessages([
            'file' => [
                __('filament.product.import_errors.invalid_option', [
                    'row' => $rowNumber,
                    'field' => $this->columnLabel('type'),
                ]),
            ],
        ]);
    }

    protected function resolveTypeVat(mixed $value, int $rowNumber): int
    {
        $normalized = $this->normalizeHeading((string) $value);

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'file' => [
                    __('filament.product.import_errors.required_field', [
                        'row' => $rowNumber,
                        'field' => $this->columnLabel('type_vat'),
                    ]),
                ],
            ]);
        }

        if (is_numeric($value) && TypeVAT::tryFrom((int) $value)) {
            return (int) $value;
        }

        foreach (TypeVAT::cases() as $case) {
            if ($this->normalizeHeading($case->label()) === $normalized) {
                return $case->value;
            }
        }

        throw ValidationException::withMessages([
            'file' => [
                __('filament.product.import_errors.invalid_option', [
                    'row' => $rowNumber,
                    'field' => $this->columnLabel('type_vat'),
                ]),
            ],
        ]);
    }

    protected function resolveBoolean(mixed $value): bool
    {
        $normalized = $this->normalizeHeading((string) $value);

        return match ($normalized) {
            '', '1', 'true', 'co', 'yes', 'dang_kinh_doanh', 'enabled' => true,
            '0', 'false', 'khong', 'no', 'ngung_kinh_doanh', 'disabled' => false,
            default => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
        };
    }

    protected function formatTypeForExport(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return ProductField::getLabel((int) $value);
        }

        return (string) $value;
    }

    protected function formatTypeVatForExport(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return TypeVAT::tryFrom((int) $value)?->label() ?? (string) $value;
    }

    protected function requiredColumns(): array
    {
        return [
            'name',
            'sku',
            'weight',
            'cost_price',
            'sale_price',
            'type',
            'length',
            'width',
            'height',
            'quantity',
            'type_vat',
            'vat_rate',
        ];
    }

    protected function resolveColumnFromHeading(string $heading): ?string
    {
        if ($heading === '') {
            return null;
        }

        $aliases = [
            'organization_id' => 'organization_id',
            'to_chuc' => 'organization_id',
            'name' => 'name',
            'ten_san_pham' => 'name',
            'sku' => 'sku',
            'ma_sku' => 'sku',
            'unit' => 'unit',
            'don_vi_tinh' => 'unit',
            'weight' => 'weight',
            'khoi_luong_g' => 'weight',
            'cost_price' => 'cost_price',
            'gia_nhap' => 'cost_price',
            'sale_price' => 'sale_price',
            'gia_ban' => 'sale_price',
            'barcode' => 'barcode',
            'ma_vach' => 'barcode',
            'type' => 'type',
            'danh_muc' => 'type',
            'length' => 'length',
            'lenght' => 'length',
            'chieu_dai_cm' => 'length',
            'width' => 'width',
            'chieu_rong_cm' => 'width',
            'height' => 'height',
            'chieu_cao_cm' => 'height',
            'quantity' => 'quantity',
            'so_luong_ton_kho' => 'quantity',
            'type_vat' => 'type_vat',
            'loai_vat' => 'type_vat',
            'vat_rate' => 'vat_rate',
            'vat' => 'vat_rate',
            'vat_percent' => 'vat_rate',
            'is_business_product' => 'is_business_product',
            'trang_thai_kinh_doanh' => 'is_business_product',
            'id' => 'id',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'thoi_diem_tao' => 'created_at',
            'thoi_diem_cap_nhat' => 'updated_at',
        ];

        return $aliases[$heading] ?? null;
    }

    protected function normalizeHeading(string $heading): string
    {
        return Str::of($heading)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    protected function columnLabel(string $column): string
    {
        return match ($column) {
            'organization_id' => __('filament.product.organization'),
            'name' => __('filament.product.name'),
            'sku' => __('filament.product.code_sku'),
            'unit' => __('filament.product.unit'),
            'weight' => __('filament.product.weight'),
            'cost_price' => __('filament.product.cost_price'),
            'sale_price' => __('filament.product.sale_price'),
            'barcode' => __('filament.product.barcode'),
            'type' => __('filament.product.type'),
            'length' => __('filament.product.length'),
            'width' => __('filament.product.width'),
            'height' => __('filament.product.height'),
            'quantity' => __('filament.product.quantity'),
            'type_vat' => __('filament.product.type_vat'),
            'vat_rate' => __('filament.product.vat_percent'),
            'is_business_product' => __('filament.product.business'),
            default => $column,
        };
    }
}
