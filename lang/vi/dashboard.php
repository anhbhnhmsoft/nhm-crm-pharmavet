<?php
return [
    'title' => 'Thống kê',
    'navigation_label' => 'Bảng điều khiển',
    'select_gap' => [
        'title_fund' => 'Giao dịch quỹ',
        'heading' => 'Chọn khoản thời gian thống kê'
    ],
    'stats' => [
        'current_balance' => 'Số dư hiện tại',
        'no_fund' => 'Chưa có quỹ',
        'increase' => 'Tăng',
        'decrease' => 'Giảm',
        'total_deposit' => 'Tổng nạp',
        'total_withdraw' => 'Tổng rút',
        'total_transactions' => 'Tổng giao dịch',
        'pending_transactions' => 'giao dịch đang chờ xử lý',
        'all_completed' => 'Tất cả đã hoàn thành',
        'days_30' => '30 ngày',
    ],
    'chart' => [
        'balance' => 'Số dư',
        'deposit' => 'Nạp tiền',
        'withdraw' => 'Rút tiền',
        'filters' => [
            '7' => '7 ngày',
            '14' => '14 ngày',
            '30' => '30 ngày',
            '90' => '90 ngày',
        ],
        'y_axis_balance' => 'Số dư (VND)',
        'y_axis_transaction' => 'Giao dịch (VND)',
    ],
    'select' => [
        'start_date_label' => 'Ngày bắt đầu',
        'end_date_label' => 'Ngày kết thúc',
        'start_date_required' => 'Vui lòng chọn ngày bắt đầu',
        'start_date_max_date' => 'Ngày bắt đầu phải nhỏ hơn hoặc bằng ngày kết thúc',
        'end_date_required' => 'Vui lòng chọn ngày kết thúc',
        'end_date_min_date' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu',
    ],

    // === Sales & Orders ===
    'order_stats' => [
        'total_revenue' => 'Tổng doanh thu',
        'total_orders' => 'Số đơn hàng',
        'pending_orders' => 'Đơn chờ xử lý',
        'completed_orders' => 'Đơn hoàn thành',
        'cancelled_orders' => 'Đơn đã hủy',
        'shipping_orders' => 'Đang giao hàng',
        'in_period' => 'Trong khoảng thời gian',
    ],
    'order_chart' => [
        'heading' => 'Biểu đồ doanh thu',
        'revenue' => 'Doanh thu',
        'order_count' => 'Số đơn',
        'y_axis_revenue' => 'Doanh thu (VND)',
        'y_axis_orders' => 'Số đơn',
    ],
    'top_products' => [
        'heading' => 'Top sản phẩm bán chạy',
        'quantity' => 'Số lượng bán',
        'revenue' => 'Doanh thu',
    ],
    'order_status' => [
        'heading' => 'Phân bố trạng thái đơn',
        'unknown' => 'Không xác định',
    ],

    // === Leads & Customers ===
    'lead_stats' => [
        'total_leads' => 'Tổng Lead',
        'new_leads' => 'Lead mới',
        'conversion_rate' => 'Tỷ lệ chuyển đổi',
        'unassigned_leads' => 'Chưa phân bổ',
        'leads_with_order' => 'lead đã chốt đơn',
        'no_unassigned' => 'Tất cả đã được phân bổ',
        'unassigned_count' => 'lead chưa được phân bổ',
    ],
    'customer_growth' => [
        'heading' => 'Tăng trưởng khách hàng',
        'new_customers' => 'Khách mới',
        'duplicate_customers' => 'Khách trùng',
        'old_customers' => 'Khách cũ quay lại',
        'y_axis' => 'Số lượng',
    ],
];
