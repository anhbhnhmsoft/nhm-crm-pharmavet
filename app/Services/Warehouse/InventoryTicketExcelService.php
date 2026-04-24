<?php

namespace App\Services\Warehouse;

use App\Common\Constants\Warehouse\TypeTicket;
use App\Models\InventoryTicketDetail;
use App\Models\Product;
use App\Models\ProductWarehouse;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class InventoryTicketExcelService
{
    public function templateHeadings(): array
    {
        $headings = [
            __('warehouse.ticket.excel.columns.product'),
            __('warehouse.navigation.product_name'),
            __('warehouse.ticket.form.quantity'),
        ];

        if ($this->usesAdvancedInventoryColumns()) {
            $headings = array_merge($headings, [
                __('warehouse.order.form.price'),
                __('warehouse.ticket.form.batch_no'),
                __('warehouse.ticket.form.expired_at'),
            ]);
        }

        return array_merge($headings, [
            __('warehouse.ticket.form.current_quantity'),
            __('warehouse.ticket.form.pending_quantity_display'),
            __('warehouse.reports.available_stock'),
        ]);
    }

    public function templateRows(): array
    {
        $row = ['SKU-001', __('warehouse.ticket.excel.sample_product_name'), 1];

        if ($this->usesAdvancedInventoryColumns()) {
            $row = array_merge($row, [0, '', '']);
        }

        return [array_merge($row, [0, 0, 0])];
    }

    public function buildExportRows(array $details): array
    {
        $productIds = collect($details)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get(['id', 'sku', 'name'])
            ->keyBy('id');

        return collect($details)->map(function (array $detail) use ($products): array {
            $product = $products->get((int) ($detail['product_id'] ?? 0));

            $row = [
                $product?->sku ?? '',
                $product?->name ?? '',
                (float) ($detail['quantity'] ?? 0),
            ];

            if ($this->usesAdvancedInventoryColumns()) {
                $row[] = (float) ($detail['unit_price'] ?? 0);
                $row[] = (string) ($detail['batch_no'] ?? '');
                $row[] = (string) ($detail['expired_at'] ?? '');
            }

            $row[] = (float) ($detail['stock_quantity_display'] ?? 0);
            $row[] = (float) ($detail['pending_quantity_display'] ?? 0);
            $row[] = (float) ($detail['current_quantity'] ?? 0);

            return $row;
        })->all();
    }

    public function exportHeadings(): array
    {
        $headings = [
            __('warehouse.ticket.excel.columns.product'),
            __('warehouse.navigation.product_name'),
            __('warehouse.ticket.form.quantity'),
        ];

        if ($this->usesAdvancedInventoryColumns()) {
            $headings = array_merge($headings, [
                __('warehouse.order.form.price'),
                __('warehouse.ticket.form.batch_no'),
                __('warehouse.ticket.form.expired_at'),
            ]);
        }

        $headings = array_merge($headings, [
            __('warehouse.ticket.form.current_quantity'),
            __('warehouse.ticket.form.pending_quantity_display'),
            __('warehouse.reports.available_stock'),
        ]);

        return $headings;
    }

    public function resolveExportDetails(mixed $details, mixed $fallbackDetails = []): array
    {
        $normalizedDetails = $this->normalizeExportDetailSource($details);

        if ($normalizedDetails === []) {
            $normalizedDetails = $this->normalizeExportDetailSource($fallbackDetails);
        }

        return collect($normalizedDetails)
            ->map(function (array $detail): array {
                $expiredAt = $detail['expired_at'] ?? null;

                if ($expiredAt instanceof Carbon) {
                    $expiredAt = $expiredAt->toDateString();
                } elseif ($expiredAt instanceof \DateTimeInterface) {
                    $expiredAt = $expiredAt->format('Y-m-d');
                } elseif (filled($expiredAt)) {
                    try {
                        $expiredAt = Carbon::parse((string) $expiredAt)->toDateString();
                    } catch (\Throwable) {
                        $expiredAt = (string) $expiredAt;
                    }
                } else {
                    $expiredAt = null;
                }

                return [
                    'product_id' => (int) data_get($detail, 'product_id', 0),
                    'quantity' => (float) (data_get($detail, 'quantity', 0) ?: 0),
                    'unit_price' => (float) (data_get($detail, 'unit_price', 0) ?: 0),
                    'batch_no' => filled(data_get($detail, 'batch_no'))
                        ? trim((string) data_get($detail, 'batch_no'))
                        : null,
                    'expired_at' => $expiredAt,
                    'current_quantity' => (float) (
                        data_get($detail, 'current_quantity')
                        ?? data_get($detail, 'stock_quantity_display')
                        ?? 0
                    ),
                ];
            })
            ->filter(fn(array $detail): bool => $detail['product_id'] > 0)
            ->values()
            ->all();
    }

    public function importRows(mixed $file, array $ticketState, int $organizationId): array
    {
        $sheets = Excel::toArray(new class {
        }, $file);
        $rows = $sheets[0] ?? [];

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => __('warehouse.ticket.excel.errors.no_rows_to_import'),
            ]);
        }

        $headerRow = array_shift($rows) ?? [];
        $headerMap = $this->validateAndBuildHeaderMap($headerRow);

        $resolvedRows = [];
        $type = (int) ($ticketState['type'] ?? 0);
        $warehouseId = $this->resolveWarehouseId($ticketState);

        if ($type <= 0) {
            throw ValidationException::withMessages([
                'file' => __('warehouse.ticket.excel.errors.type_required_before_import'),
            ]);
        }

        if ($warehouseId <= 0) {
            throw ValidationException::withMessages([
                'file' => __('warehouse.ticket.excel.errors.warehouse_required_before_import'),
            ]);
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $rowValues = $this->extractRowValues($row, $headerMap);

            if ($this->isEmptyRow($rowValues)) {
                continue;
            }

            $sku = trim((string) ($rowValues['sku'] ?? ''));
            $productName = trim((string) ($rowValues['product_name'] ?? ''));
            $productIdentifier = $sku !== '' ? $sku : $productName;
            $rawQuantity = $rowValues['quantity'] ?? null;
            $quantity = $this->normalizeNumeric($rawQuantity);
            $rawUnitPrice = $rowValues['unit_price'] ?? 0;
            $unitPrice = $this->normalizeNumeric($rawUnitPrice);
            $batchNo = trim((string) ($rowValues['batch_no'] ?? ''));
            $expiredAt = $this->normalizeDate($rowValues['expired_at'] ?? null, $rowNumber);

            $rowErrors = [];

            if ($productIdentifier === '') {
                $rowErrors[] = __('warehouse.ticket.form.product');
            }

            if ($this->isBlankValue($rawQuantity) || $quantity === null || $quantity <= 0) {
                $rowErrors[] = __('warehouse.ticket.form.quantity');
            }

            if (! $this->isBlankValue($rawUnitPrice) && ($unitPrice === null || $unitPrice < 0)) {
                $rowErrors[] = __('warehouse.order.form.price');
            }

            if ($rowErrors !== []) {
                throw ValidationException::withMessages([
                    'file' => __('warehouse.ticket.excel.errors.row_invalid_fields', [
                        'row' => $rowNumber,
                        'fields' => implode(', ', array_unique($rowErrors)),
                    ]),
                ]);
            }

            $product = $this->resolveProduct($productIdentifier, $organizationId, $rowNumber);

            if (! $product) {
                throw ValidationException::withMessages([
                    'file' => __('warehouse.ticket.excel.errors.product_not_found', [
                        'row' => $rowNumber,
                        'sku' => $productIdentifier,
                    ]),
                ]);
            }

            $stockSnapshot = $this->resolveStockSnapshot((int) $product->id, $warehouseId);
            $currentQuantity = $stockSnapshot['available'];

            if (
                in_array($type, [TypeTicket::EXPORT->value, TypeTicket::TRANSFER->value], true)
                && $currentQuantity < $quantity
            ) {
                throw ValidationException::withMessages([
                    'file' => __('warehouse.ticket.excel.errors.insufficient_stock', [
                        'row' => $rowNumber,
                        'sku' => $product->sku ?: $productIdentifier,
                        'stock' => $currentQuantity,
                    ]),
                ]);
            }

            $resolvedRows[] = [
                'product_id' => (int) $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'batch_no' => $batchNo !== '' ? $batchNo : null,
                'expired_at' => $expiredAt,
                'stock_quantity_display' => $stockSnapshot['quantity'],
                'pending_quantity_display' => $stockSnapshot['pending'],
                'current_quantity' => $currentQuantity,
            ];
        }

        if ($resolvedRows === []) {
            throw ValidationException::withMessages([
                'file' => __('warehouse.ticket.excel.errors.no_rows_to_import'),
            ]);
        }

        return $resolvedRows;
    }

    public function formatImportException(\Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            $messages = collect($exception->errors())
                ->flatten()
                ->filter(fn(mixed $message): bool => is_string($message) && filled(trim($message)))
                ->unique()
                ->values();

            if ($messages->isNotEmpty()) {
                return $messages->implode(' | ');
            }
        }

        $message = trim((string) $exception->getMessage());

        return $message !== ''
            ? $message
            : __('warehouse.ticket.excel.errors.import_unexpected');
    }

    protected function buildHeaderMap(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $heading) {
            $normalized = $this->normalizeHeading((string) $heading);

            if ($normalized !== '') {
                $map[$normalized] = $index;
            }
        }

        return $map;
    }

    protected function normalizeExportDetailSource(mixed $details): array
    {
        if ($details instanceof Enumerable) {
            $details = $details->all();
        }

        if (! is_array($details)) {
            return [];
        }

        return collect($details)
            ->map(function (mixed $detail): array {
                if ($detail instanceof InventoryTicketDetail) {
                    return [
                        'product_id' => $detail->product_id,
                        'quantity' => $detail->quantity,
                        'unit_price' => $detail->unit_price,
                        'batch_no' => $detail->batch_no,
                        'expired_at' => $detail->getRawOriginal('expired_at')
                            ?: $detail->expired_at?->toDateString(),
                        'current_quantity' => $detail->current_quantity,
                    ];
                }

                if ($detail instanceof Collection) {
                    return $detail->toArray();
                }

                if (is_array($detail)) {
                    return $detail;
                }

                if (is_object($detail) && method_exists($detail, 'toArray')) {
                    return (array) $detail->toArray();
                }

                if (is_object($detail)) {
                    return (array) $detail;
                }

                return [];
            })
            ->values()
            ->all();
    }

    protected function validateAndBuildHeaderMap(array $headerRow): array
    {
        $columnAliases = $this->importColumnAliases();
        $headerMap = [];
        $invalidColumns = collect();

        foreach ($headerRow as $index => $heading) {
            $originalHeading = trim((string) $heading);

            if ($originalHeading === '') {
                continue;
            }

            $normalizedHeading = $this->normalizeHeading($originalHeading);

            if ($normalizedHeading === '') {
                continue;
            }

            $canonicalColumn = $columnAliases[$normalizedHeading] ?? null;

            if ($canonicalColumn === null) {
                $invalidColumns->push($originalHeading);

                continue;
            }

            $headerMap[$canonicalColumn] ??= $index;
        }

        $messages = [];
        $missingColumns = $this->requiredImportColumns()
            ->reject(fn(string $requiredColumn): bool => array_key_exists($requiredColumn, $headerMap))
            ->values();

        if ($missingColumns->isNotEmpty()) {
            $messages[] = __('warehouse.ticket.excel.errors.missing_columns', [
                'columns' => $this->formatColumnLabels($missingColumns),
            ]);
        }

        if ($invalidColumns->isNotEmpty()) {
            $messages[] = __('warehouse.ticket.excel.errors.invalid_columns', [
                'columns' => $this->formatRawColumnNames($invalidColumns),
            ]);
        }

        if ($messages !== []) {
            throw ValidationException::withMessages([
                'file' => $messages,
            ]);
        }

        return $headerMap;
    }

    protected function extractRowValues(array $row, array $headerMap): array
    {
        $values = [];

        foreach ($headerMap as $column => $index) {
            $values[$column] = Arr::get($row, $index);
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

    protected function normalizeNumeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    protected function normalizeDate(mixed $value, int $rowNumber): ?string
    {
        if ($this->isBlankValue($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => __('warehouse.ticket.excel.errors.expired_at_invalid', ['row' => $rowNumber]),
            ]);
        }
    }

    protected function isBlankValue(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    protected function formatColumnLabels(Collection $columns): string
    {
        return $columns
            ->map(fn(string $column): string => $this->resolveColumnLabel($column))
            ->implode(', ');
    }

    protected function formatRawColumnNames(Collection $columns): string
    {
        return $columns
            ->map(fn(string $column): string => '"' . trim($column) . '"')
            ->implode(', ');
    }

    protected function requiredImportColumns(): Collection
    {
        return collect(['sku', 'quantity']);
    }

    protected function acceptedImportColumns(): Collection
    {
        return collect([
            'sku',
            'quantity',
            'unit_price',
            'batch_no',
            'expired_at',
            'product_name',
            'stock_quantity_display',
            'pending_quantity_display',
            'available_stock',
        ]);
    }

    protected function resolveColumnLabel(string $column): string
    {
        return match ($column) {
            'sku' => __('warehouse.ticket.form.product'),
            'quantity' => __('warehouse.ticket.form.quantity'),
            'unit_price' => __('warehouse.order.form.price'),
            'batch_no' => __('warehouse.ticket.form.batch_no'),
            'expired_at' => __('warehouse.ticket.form.expired_at'),
            'product_name' => __('warehouse.navigation.product_name'),
            'stock_quantity_display' => __('warehouse.ticket.form.current_quantity'),
            'pending_quantity_display' => __('warehouse.ticket.form.pending_quantity_display'),
            'available_stock' => __('warehouse.reports.available_stock'),
            default => $column,
        };
    }

    protected function normalizeHeading(string $heading): string
    {
        $normalized = Str::ascii(Str::lower(trim($heading)));
        $normalized = str_replace(['-', ' ', '/', '\\'], '_', $normalized);

        return preg_replace('/[^a-z0-9_]+/', '', $normalized) ?? '';
    }

    protected function importColumnAliases(): array
    {
        $aliases = [];

        foreach ($this->acceptedImportColumns() as $column) {
            $aliases[$this->normalizeHeading($column)] = $column;
            $aliases[$this->normalizeHeading($this->resolveColumnLabel($column))] = $column;
        }

        $aliases[$this->normalizeHeading(__('warehouse.ticket.excel.columns.product'))] = 'sku';
        $aliases[$this->normalizeHeading('SKU')] = 'sku';
        $aliases[$this->normalizeHeading('product_sku')] = 'sku';
        $aliases[$this->normalizeHeading('product_code')] = 'sku';
        $aliases[$this->normalizeHeading('stock_quantity')] = 'stock_quantity_display';
        $aliases[$this->normalizeHeading('current_stock')] = 'stock_quantity_display';
        $aliases[$this->normalizeHeading('pending_quantity')] = 'pending_quantity_display';
        $aliases[$this->normalizeHeading('current_quantity')] = 'available_stock';

        return $aliases;
    }

    protected function resolveWarehouseId(array $ticketState): int
    {
        $type = (int) ($ticketState['type'] ?? 0);

        return match ($type) {
            TypeTicket::TRANSFER->value => (int) ($ticketState['source_warehouse_id'] ?? 0),
            default => (int) ($ticketState['warehouse_id'] ?? 0),
        };
    }

    protected function resolveProduct(string $identifier, int $organizationId, int $rowNumber): ?Product
    {
        $product = Product::query()
            ->where('organization_id', $organizationId)
            ->where('sku', $identifier)
            ->first();

        if ($product) {
            return $product;
        }

        $matches = Product::query()
            ->where('organization_id', $organizationId)
            ->where('name', $identifier)
            ->limit(2)
            ->get();

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'file' => __('warehouse.ticket.excel.errors.product_ambiguous', [
                    'row' => $rowNumber,
                    'product' => $identifier,
                ]),
            ]);
        }

        return $matches->first();
    }

    protected function resolveStockSnapshot(int $productId, int $warehouseId): array
    {
        if ($warehouseId <= 0) {
            return [
                'quantity' => 0,
                'pending' => 0,
                'available' => 0,
            ];
        }

        $stock = ProductWarehouse::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first(['quantity', 'pending_quantity']);

        $quantity = (int) ($stock?->quantity ?? 0);
        $pending = (int) ($stock?->pending_quantity ?? 0);

        return [
            'quantity' => $quantity,
            'pending' => $pending,
            'available' => max(0, $quantity - $pending),
        ];
    }

    protected function usesAdvancedInventoryColumns(): bool
    {
        return (bool) config('warehouse.features.advanced_inventory_v1', false);
    }
}
