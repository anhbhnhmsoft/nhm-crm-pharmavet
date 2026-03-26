<?php

namespace App\Exports;

use App\Common\Constants\Organization\FundTransactionType;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FundLedgerExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        protected Collection $rows
    ) {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            __('accounting.fund_transaction.transaction_date'),
            __('accounting.fund_transaction.transaction_code'),
            __('accounting.fund_transaction.purpose'),
            __('accounting.fund_transaction.counterparty_name'),
            __('accounting.fund_ledger.in_amount'),
            __('accounting.fund_ledger.out_amount'),
            __('accounting.fund_transaction.currency'),
            __('accounting.fund_transaction.balance_after'),
            __('accounting.fund_transaction.description'),
            __('accounting.fund_transaction.note'),
        ];
    }

    public function map($row): array
    {
        return [
            optional($row->transaction_date)->format('d/m/Y'),
            $row->transaction_code,
            $row->purpose,
            $row->counterparty_name,
            (int) $row->type === FundTransactionType::DEPOSIT->value ? (float) $row->amount : '',
            (int) $row->type === FundTransactionType::WITHDRAW->value ? (float) $row->amount : '',
            $row->currency,
            (float) $row->balance_after,
            $row->description,
            $row->note,
        ];
    }
}
