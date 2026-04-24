<?php

namespace Tests\Unit\Warehouse;

use App\Common\Constants\Warehouse\TypeTicket;
use App\Services\Warehouse\InventoryTicketExcelService;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class InventoryTicketExcelServiceTest extends TestCase
{
    public function test_template_headings_match_basic_mode_when_advanced_inventory_is_disabled(): void
    {
        config()->set('warehouse.features.advanced_inventory_v1', false);

        $service = new InventoryTicketExcelService();

        $this->assertSame(['sku', 'quantity'], $service->templateHeadings());
        $this->assertSame([['SKU-001', 1]], $service->templateRows());
        $this->assertSame(['sku', 'product_name', 'quantity', 'available_stock'], $service->exportHeadings());
    }

    public function test_template_headings_include_optional_inventory_columns_when_advanced_inventory_is_enabled(): void
    {
        config()->set('warehouse.features.advanced_inventory_v1', true);

        $service = new InventoryTicketExcelService();

        $this->assertSame(
            ['sku', 'quantity', 'unit_price', 'batch_no', 'expired_at'],
            $service->templateHeadings(),
        );

        $this->assertSame(
            ['sku', 'product_name', 'quantity', 'unit_price', 'batch_no', 'expired_at', 'available_stock'],
            $service->exportHeadings(),
        );
    }

    public function test_import_rows_rejects_file_without_importable_data_rows(): void
    {
        config()->set('warehouse.features.advanced_inventory_v1', false);

        $service = new InventoryTicketExcelService();
        $path = $this->createTemporaryExcelFile([
            ['sku', 'quantity'],
        ]);

        try {
            $service->importRows($path, ['type' => TypeTicket::IMPORT->value], 1);
            $this->fail('Expected import to fail when the file only contains a header row.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('warehouse.ticket.excel.errors.no_rows_to_import'),
                $exception->errors()['file'][0] ?? null,
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_import_rows_reports_missing_and_invalid_header_columns(): void
    {
        config()->set('warehouse.features.advanced_inventory_v1', false);

        $service = new InventoryTicketExcelService();
        $path = $this->createTemporaryExcelFile([
            ['sku', 'quantitiy'],
        ]);

        try {
            $service->importRows($path, ['type' => TypeTicket::IMPORT->value], 1);
            $this->fail('Expected import to fail when the header contains invalid column names.');
        } catch (ValidationException $exception) {
            $messages = $exception->errors()['file'] ?? [];

            $this->assertContains(
                __('warehouse.ticket.excel.errors.missing_columns', [
                    'columns' => __('warehouse.ticket.form.quantity'),
                ]),
                $messages,
            );

            $this->assertContains(
                __('warehouse.ticket.excel.errors.invalid_columns', [
                    'columns' => '"quantitiy"',
                ]),
                $messages,
            );
        } finally {
            @unlink($path);
        }
    }

    private function createTemporaryExcelFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value);
            }
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'inventory-ticket-excel-');

        if ($temporaryPath === false) {
            $this->fail('Unable to create a temporary file for Excel import test.');
        }

        $path = $temporaryPath . '.xlsx';
        @rename($temporaryPath, $path);

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }
}
