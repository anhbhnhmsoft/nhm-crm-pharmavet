<?php

namespace App\Services\Warehouse;

use App\Common\Constants\Warehouse\TypeTicket;
use App\Models\Product;
use App\Models\ProductWarehouse;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class InventoryTicketExcelService
{
    public function templateHeadings(): array
    {
        return ['sku', 'quantity', 'unit_price', 'batch_no', 'expired_at'];
    }

    public function templateRows(): array
    {
        return [
            ['SKU-001', 1, 0, '', ''],
        ];
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

            return [
                $product?->sku ?? '',
                $product?->name ?? '',
                (float) ($detail['quantity'] ?? 0),
                (float) ($detail['unit_price'] ?? 0),
                (string) ($detail['batch_no'] ?? ''),
                (string) ($detail['expired_at'] ?? ''),
                (float) ($detail['current_quantity'] ?? 0),
            ];
        })->all();
    }

    public function exportHeadings(): array
    {
        return [
            'sku',
            'product_name',
            'quantity',
            'unit_price',
            'batch_no',
            'expired_at',
            'available_stock',
        ];
    }

    public function importRows(mixed $file, array $ticketState, int $organizationId): array
    {
        $sheets = Excel::toArray(new class {
        }, $file);
        $rows = $sheets[0] ?? [];

        if ($rows === []) {
            return [];
        }

        $headerRow = array_shift($rows) ?? [];
        $headerMap = $this->buildHeaderMap($headerRow);

        $missingColumns = collect(['sku', 'quantity'])
            ->reject(fn(string $requiredColumn): bool => array_key_exists($requiredColumn, $headerMap))
            ->values();

        if ($missingColumns->isNotEmpty()) {
            throw ValidationException::withMessages([
                'file' => __('warehouse.ticket.excel.errors.missing_columns', [
                    'columns' => $this->formatColumnLabels($missingColumns),
                ]),
            ]);
        }

        $resolvedRows = [];
        $type = (int) ($ticketState['type'] ?? 0);
        $warehouseId = $this->resolveWarehouseId($ticketState);

        if ($type <= 0) {
            throw ValidationException::withMessages([
                'file' => __('warehouse.ticket.excel.errors.type_required_before_import'),
            ]);
        }

        if (in_array($type, [TypeTicket::EXPORT->value, TypeTicket::TRANSFER->value], true) && $warehouseId <= 0) {
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
            $rawQuantity = $rowValues['quantity'] ?? null;
            $quantity = $this->normalizeNumeric($rawQuantity);
            $rawUnitPrice = $rowValues['unit_price'] ?? 0;
            $unitPrice = $this->normalizeNumeric($rawUnitPrice);
            $batchNo = trim((string) ($rowValues['batch_no'] ?? ''));
            $expiredAt = $this->normalizeDate($rowValues['expired_at'] ?? null, $rowNumber);

            $rowErrors = [];

            if ($sku === '') {
                $rowErrors[] = 'SKU';
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

            $product = Product::query()
                ->where('organization_id', $organizationId)
                ->where('sku', $sku)
                ->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    'file' => __('warehouse.ticket.excel.errors.product_not_found', [
                        'row' => $rowNumber,
                        'sku' => $sku,
                    ]),
                ]);
            }

            $currentQuantity = $this->resolveCurrentQuantity((int) $product->id, $warehouseId);

            if (
                in_array($type, [TypeTicket::EXPORT->value, TypeTicket::TRANSFER->value], true)
                && $currentQuantity < $quantity
            ) {
                throw ValidationException::withMessages([
                    'file' => __('warehouse.ticket.excel.errors.insufficient_stock', [
                        'row' => $rowNumber,
                        'sku' => $sku,
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
                'current_quantity' => $currentQuantity,
            ];
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
            $normalized = strtolower(trim((string) $heading));

            if ($normalized !== '') {
                $map[$normalized] = $index;
            }
        }

        return $map;
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

    protected function resolveColumnLabel(string $column): string
    {
        return match ($column) {
            'sku' => 'SKU',
            'quantity' => __('warehouse.ticket.form.quantity'),
            'unit_price' => __('warehouse.order.form.price'),
            'batch_no' => __('warehouse.ticket.form.batch_no'),
            'expired_at' => __('warehouse.ticket.form.expired_at'),
            default => $column,
        };
    }

    protected function resolveWarehouseId(array $ticketState): int
    {
        $type = (int) ($ticketState['type'] ?? 0);

        return match ($type) {
            TypeTicket::TRANSFER->value => (int) ($ticketState['source_warehouse_id'] ?? 0),
            default => (int) ($ticketState['warehouse_id'] ?? 0),
        };
    }

    protected function resolveCurrentQuantity(int $productId, int $warehouseId): float
    {
        if ($warehouseId <= 0) {
            return 0;
        }

        $stock = ProductWarehouse::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first(['quantity', 'pending_quantity']);

        return (float) max(
            0,
            (int) ($stock?->quantity ?? 0) - (int) ($stock?->pending_quantity ?? 0)
        );
    }
}
