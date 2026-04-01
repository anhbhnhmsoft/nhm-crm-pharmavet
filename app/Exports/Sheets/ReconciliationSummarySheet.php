<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReconciliationSummarySheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    protected $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        $summary = [];

        $warehouseData = [];
        $saleData = [];

        foreach ($this->rows as $row) {
            $warehouseName = $row->order?->warehouse?->name ?? 'Không xác định';
            $saleName = $row->order?->createdBy?->name ?? 'Không xác định';

            if (!isset($warehouseData[$warehouseName])) {
                $warehouseData[$warehouseName] = [
                    'group' => 'Kho',
                    'name' => $warehouseName,
                    'count' => 0,
                    'cod' => 0,
                    'fee' => 0,
                    'receivable' => 0,
                ];
            }

            if (!isset($saleData[$saleName])) {
                $saleData[$saleName] = [
                    'group' => 'Sale',
                    'name' => $saleName,
                    'count' => 0,
                    'cod' => 0,
                    'fee' => 0,
                    'receivable' => 0,
                ];
            }

            $warehouseData[$warehouseName]['count']++;
            $warehouseData[$warehouseName]['cod'] += (float)$row->cod_amount;
            $warehouseData[$warehouseName]['fee'] += (float)$row->shipping_fee + (float)$row->storage_fee;
            $warehouseData[$warehouseName]['receivable'] += (float)$row->total_fee;

            $saleData[$saleName]['count']++;
            $saleData[$saleName]['cod'] += (float)$row->cod_amount;
            $saleData[$saleName]['fee'] += (float)$row->shipping_fee + (float)$row->storage_fee;
            $saleData[$saleName]['receivable'] += (float)$row->total_fee;
        }

        $result = collect();

        $result->push([
            __('accounting.reconciliation.classification'), 
            __('accounting.reconciliation.partner_name'), 
            __('accounting.report.total_orders'), 
            __('accounting.reconciliation.cod_amount'), 
            __('accounting.reconciliation.total_fee'), 
            __('accounting.reconciliation.total_amount')
        ]);
        
        foreach ($warehouseData as $data) {
            $result->push(array_values($data));
        }

        $result->push(['', '', '', '', '', '']);

        foreach ($saleData as $data) {
            $result->push(array_values($data));
        }

        return $result;
    }

    public function title(): string
    {
        return __('accounting.report.summary');
    }

    public function headings(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
