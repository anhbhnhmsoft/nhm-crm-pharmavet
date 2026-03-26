<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ __('accounting.fund_ledger.title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #333; padding: 6px; }
        th { background: #f0f0f0; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
<h3>{{ __('accounting.fund_ledger.title') }}</h3>
<p>
    {{ __('accounting.report.from_date') }}: {{ $filters['from_date'] ?? '' }}
    -
    {{ __('accounting.report.to_date') }}: {{ $filters['to_date'] ?? '' }}
</p>
<p>
    {{ __('accounting.fund_ledger.total_in') }}: {{ number_format((float) ($summary['total_in'] ?? 0), 2) }} |
    {{ __('accounting.fund_ledger.total_out') }}: {{ number_format((float) ($summary['total_out'] ?? 0), 2) }} |
    {{ __('accounting.fund_ledger.balance') }}: {{ number_format((float) ($summary['balance'] ?? 0), 2) }}
</p>
<table>
    <thead>
    <tr>
        <th>{{ __('accounting.fund_transaction.transaction_date') }}</th>
        <th>{{ __('accounting.fund_transaction.transaction_code') }}</th>
        <th>{{ __('accounting.fund_transaction.purpose') }}</th>
        <th>{{ __('accounting.fund_transaction.counterparty_name') }}</th>
        <th class="right">{{ __('accounting.fund_ledger.in_amount') }}</th>
        <th class="right">{{ __('accounting.fund_ledger.out_amount') }}</th>
        <th>{{ __('accounting.fund_transaction.currency') }}</th>
        <th class="right">{{ __('accounting.fund_transaction.balance_after') }}</th>
        <th>{{ __('accounting.fund_transaction.note') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
        <tr>
            <td>{{ optional($row->transaction_date)->format('d/m/Y') }}</td>
            <td>{{ $row->transaction_code }}</td>
            <td>{{ $row->purpose }}</td>
            <td>{{ $row->counterparty_name }}</td>
            <td class="right">{{ (int) $row->type === \App\Common\Constants\Organization\FundTransactionType::DEPOSIT->value ? number_format((float) $row->amount, 2) : '' }}</td>
            <td class="right">{{ (int) $row->type === \App\Common\Constants\Organization\FundTransactionType::WITHDRAW->value ? number_format((float) $row->amount, 2) : '' }}</td>
            <td>{{ $row->currency }}</td>
            <td class="right">{{ number_format((float) $row->balance_after, 2) }}</td>
            <td>{{ $row->note }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<div style="margin-top: 30px;">
    <table style="width: 100%; border: none;">
        <tr>
            <td style="width: 50%; border: none; text-align: center;">
                {{ __('accounting.fund_ledger.sign_prepared_by') }}
                <br><br><br>
                (____________________)
            </td>
            <td style="width: 50%; border: none; text-align: center;">
                {{ __('accounting.fund_ledger.sign_approved_by') }}
                <br><br><br>
                (____________________)
            </td>
        </tr>
    </table>
</div>
</body>
</html>
