<!DOCTYPE html>
<html lang="vi">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('accounting.debt_reconciliation.confirm_heading') }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .container {
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .app-name {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 10px 0;
        }
        .period {
            font-style: italic;
            font-size: 12px;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-row {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .main-table th, .main-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .main-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 10px;
        }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .font-bold { font-weight: bold; }
        .footer {
            margin-top: 50px;
        }
        .signature-table {
            width: 100%;
            border: none;
        }
        .signature-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .signature-title {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .signature-note {
            font-size: 10px;
            font-style: italic;
            color: #666;
        }
        .balance-summary {
            margin-bottom: 15px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="app-name">{{ __('accounting.debt_reconciliation.app_name') }}</div>
            <div class="title">{{ __('accounting.debt_reconciliation.confirm_heading') }}</div>
            <div class="period">
                {{ __('accounting.debt_reconciliation.from_date') }}: {{ \Carbon\Carbon::parse($filters['from_date'])->format('d/m/Y') }} 
                - {{ __('accounting.debt_reconciliation.to_date') }}: {{ \Carbon\Carbon::parse($filters['to_date'])->format('d/m/Y') }}
            </div>
        </div>

        <div class="info-section">
            <div class="info-row">
                <span class="info-label">{{ __('accounting.debt_reconciliation.partner_type') }}:</span>
                <span>{{ $partner_name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">{{ __('accounting.debt_reconciliation.print_at') }}:</span>
                <span>{{ now()->format('H:i d/m/Y') }}</span>
            </div>
        </div>

        <div class="balance-summary">
            <strong>{{ __('accounting.debt_reconciliation.opening_balance') }}: </strong>
            <span>{{ number_format($report['opening_balance'], 0, ',', '.') }} đ</span>
        </div>

        <table class="main-table">
            <thead>
                <tr>
                    <th width="15%">{{ __('accounting.debt_reconciliation.date') }}</th>
                    <th width="15%">{{ __('accounting.debt_reconciliation.code') }}</th>
                    <th>{{ __('accounting.debt_reconciliation.description') }}</th>
                    <th width="15%" class="text-right">{{ __('accounting.debt_reconciliation.debit') }}</th>
                    <th width="15%" class="text-right">{{ __('accounting.debt_reconciliation.credit') }}</th>
                    <th width="15%" class="text-right">{{ __('accounting.debt_reconciliation.remaining') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['transactions'] as $t)
                    <tr>
                        <td class="text-center">{{ \Carbon\Carbon::parse($t['date'])->format('d/m/Y') }}</td>
                        <td>{{ $t['code'] }}</td>
                        <td>{{ $t['description'] }}</td>
                        <td class="text-right">{{ $t['debit'] > 0 ? number_format($t['debit'], 0, ',', '.') : '-' }}</td>
                        <td class="text-right">{{ $t['credit'] > 0 ? number_format($t['credit'], 0, ',', '.') : '-' }}</td>
                        <td class="text-right font-bold">{{ number_format($t['balance'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="font-bold bg-light">
                    <td colspan="3" class="text-right">{{ __('accounting.debt_reconciliation.total') }}</td>
                    <td class="text-right">{{ $report['total_debit'] > 0 ? number_format($report['total_debit'], 0, ',', '.') : '0' }}</td>
                    <td class="text-right">{{ $report['total_credit'] > 0 ? number_format($report['total_credit'], 0, ',', '.') : '0' }}</td>
                    <td class="text-right">{{ number_format($report['closing_balance'], 0, ',', '.') }} đ</td>
                </tr>
            </tbody>
        </table>

        <div class="balance-summary" style="font-size: 14px;">
            <strong>{{ __('accounting.debt_reconciliation.closing_balance') }}: </strong>
            <span class="font-bold">{{ number_format($report['closing_balance'], 0, ',', '.') }} đ</span>
        </div>

        <div class="footer">
            <table class="signature-table">
                <tr>
                    <td>
                        <div class="signature-title">{{ __('accounting.debt_reconciliation.partner_representative') }}</div>
                        <div class="signature-note">{{ __('accounting.debt_reconciliation.signature_note') }}</div>
                    </td>
                    <td>
                        <div class="signature-title">{{ __('accounting.debt_reconciliation.chief_accountant') }}</div>
                        <div class="signature-note">{{ __('accounting.debt_reconciliation.signature_note') }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
