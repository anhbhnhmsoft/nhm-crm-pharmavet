<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReconciliationDetailSheet implements FromCollection, WithHeadings, WithTitle, WithMapping, ShouldAutoSize, WithStyles
{
    protected $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return $this->rows;
    }

    public function title(): string
    {
        return __('accounting.reconciliation.plural_label');
    }

    public function headings(): array
    {
        return [
            __('accounting.reconciliation.data_arrival_date'),
            __('accounting.reconciliation.ghn_order_code'),
            __('accounting.reconciliation.order_code'),
            __('accounting.reconciliation.sale'),
            __('accounting.reconciliation.warehouse'),
            __('accounting.reconciliation.customer_name'),
            __('accounting.reconciliation.customer_phone'),
            __('accounting.reconciliation.cod_amount'),
            __('accounting.reconciliation.shipping_fee'),
            __('accounting.reconciliation.storage_fee'),
            __('accounting.reconciliation.total_amount'),
            __('accounting.reconciliation.status'),
            __('accounting.reconciliation.accounting_note'),
        ];
    }

    public function map($row): array
    {
        return [
            $row->ghn_created_at ? $row->ghn_created_at->format('d/m/Y H:i') : ($row->created_at ? $row->created_at->format('d/m/Y H:i') : ''),
            $row->ghn_order_code,
            $row->order?->code ?? '',
            $row->order?->createdBy?->name ?? '',
            $row->order?->warehouse?->name ?? '',
            $row->order?->customer?->username ?? $row->ghn_to_name ?? '',
            $row->order?->customer?->phone ?? $row->ghn_to_phone ?? '',
            $row->cod_amount,
            $row->shipping_fee,
            $row->storage_fee,
            $row->total_fee,
            $row->ghn_status_label,
            strip_tags((string) $row->ghn_employee_note),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
