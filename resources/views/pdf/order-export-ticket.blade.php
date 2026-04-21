<!DOCTYPE html>
<html lang="vi">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('warehouse.order.print.title') }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #111827;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .container {
            padding: 24px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .app-name {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .subtitle {
            color: #4b5563;
            font-size: 10px;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 18px;
        }
        .info-grid td {
            width: 50%;
            vertical-align: top;
            padding: 2px 0;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            min-width: 110px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .table th,
        .table td {
            border: 1px solid #d1d5db;
            padding: 8px;
        }
        .table th {
            background: #f3f4f6;
            text-align: center;
            font-size: 10px;
            text-transform: uppercase;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .summary {
            text-align: right;
            font-weight: bold;
            margin-bottom: 28px;
        }
        .signatures {
            width: 100%;
            border: 0;
        }
        .signatures td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
        }
        .signature-title {
            font-weight: bold;
            margin-bottom: 60px;
        }
        .signature-name {
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="app-name">{{ config('app.name') }}</div>
            <div class="title">{{ __('warehouse.order.print.title') }}</div>
            <div class="subtitle">{{ __('warehouse.order.print.printed_at') }}: {{ $printedAt->format('H:i d/m/Y') }}</div>
        </div>

        <table class="info-grid">
            <tr>
                <td>
                    <span class="label">{{ __('warehouse.order.print.order_code') }}:</span>
                    <span>{{ $order->code }}</span>
                </td>
                <td>
                    <span class="label">{{ __('warehouse.order.print.order_status') }}:</span>
                    <span>{{ \App\Common\Constants\Order\OrderStatus::getLabel((int) $order->status) }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">{{ __('warehouse.order.print.warehouse') }}:</span>
                    <span>{{ $order->warehouse?->name ?? '-' }}</span>
                </td>
                <td>
                    <span class="label">{{ __('warehouse.order.print.customer') }}:</span>
                    <span>{{ $order->customer?->username ?? '-' }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">{{ __('warehouse.order.print.customer_phone') }}:</span>
                    <span>{{ $order->customer?->phone ?? '-' }}</span>
                </td>
                <td>
                    <span class="label">{{ __('warehouse.order.print.shipping_address') }}:</span>
                    <span>{{ $shippingAddress !== '' ? $shippingAddress : '-' }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">{{ __('warehouse.order.print.note') }}:</span>
                    <span>{{ $order->note ?: '-' }}</span>
                </td>
            </tr>
        </table>

        <table class="table">
            <thead>
                <tr>
                    <th width="8%">{{ __('warehouse.order.print.no') }}</th>
                    <th width="18%">{{ __('warehouse.order.print.product_code') }}</th>
                    <th>{{ __('warehouse.order.print.product_name') }}</th>
                    <th width="14%">{{ __('warehouse.order.print.unit') }}</th>
                    <th width="14%">{{ __('warehouse.order.print.quantity') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $index => $item)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $item->product?->sku ?: '-' }}</td>
                        <td>{{ $item->product?->name ?: ('#' . $item->product_id) }}</td>
                        <td class="text-center">{{ $item->product?->unit ?: '-' }}</td>
                        <td class="text-right">{{ number_format((float) $item->quantity, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            {{ __('warehouse.order.print.total_quantity') }}: {{ number_format((float) $totalQuantity, 0, ',', '.') }}
        </div>

        <table class="signatures">
            <tr>
                <td>
                    <div class="signature-title">{{ __('warehouse.order.print.prepared_by') }}</div>
                    <div class="signature-name">{{ $order->createdBy?->name ?: '................................' }}</div>
                </td>
                <td>
                    <div class="signature-title">{{ __('warehouse.order.print.warehouse_keeper') }}</div>
                    <div class="signature-name">................................</div>
                </td>
                <td>
                    <div class="signature-title">{{ __('warehouse.order.print.receiver') }}</div>
                    <div class="signature-name">................................</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
