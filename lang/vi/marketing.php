<?php

return [
    'report' => [
        'fanpage_title' => 'Báo cáo Fanpage',
        'generate_button' => 'Tạo báo cáo',
        'filter_section' => 'Lọc báo cáo',
        'from_date' => 'Từ ngày',
        'to_date' => 'Đến ngày',
        'error_generate' => 'Có lỗi xảy ra khi tạo báo cáo',
        'success_generate' => 'Đã tải báo cáo',
        'chart_and_report' => 'Biểu đồ & Báo cáo Fanpage',
        'no' => 'STT',
        'page_name' => 'Tên Page',
        'mkt_name' => 'Tên MKT',
        'new_customers' => 'Khách mới',
        'total_orders' => 'Số lượng đơn (A1)',
        'total_leads' => 'Lượng số',
        'conversion_rate' => 'Tỉ lệ sinh số',
        'revenue' => 'Doanh số',
        'no_data' => 'Không có dữ liệu trong thời gian này.',
    ],

    'honor_board' => [
        'navigation' => 'Phong thần bảng',
        'title' => 'Phong thần bảng',
        'watermark' => 'Bảng Vinh Danh',
        'suggestions' => 'Gợi ý',
        'top_3_badge' => 'TOP 3',
        'unknown_team' => 'Chưa gắn team',
        'unknown_source' => 'Nguồn chưa xác định',
        'unknown_entity' => 'Chưa xác định',

        'actions' => [
            'clear_search' => 'Xóa tìm kiếm',
            'close' => 'Đóng',
        ],

        'filters' => [
            'title' => 'Bộ lọc vinh danh',
            'pushsale_rule_set' => 'Chuẩn Pushsale',
            'all_pushsale' => 'Tất cả chuẩn',
            'revenue_mode' => 'Chế độ doanh số',
            'date_preset' => 'Khoảng thời gian',
            'from_date' => 'Từ ngày',
            'to_date' => 'Đến ngày',
            'search' => 'Tìm kiếm tên/nhóm',
            'search_placeholder' => 'Nhập tên nhân viên hoặc tên nhóm',
        ],

        'revenue_mode' => [
            'before_discount' => 'Doanh số trước chiết khấu',
            'after_discount' => 'Doanh số sau chiết khấu',
        ],

        'date_preset' => [
            'today' => 'Hôm nay',
            'this_week' => 'Tuần này',
            'this_month' => 'Tháng này',
            'custom' => 'Tùy chỉnh',
        ],

        'columns' => [
            'sale' => [
                'title' => 'Sale',
                'subtitle' => 'Xếp hạng theo nhóm Sale',
            ],
            'telesale' => [
                'title' => 'Telesale',
                'subtitle' => 'Xếp hạng theo nhân viên Telesale',
            ],
            'marketing' => [
                'title' => 'Marketing',
                'subtitle' => 'Xếp hạng theo nguồn Marketing',
            ],
        ],

        'table' => [
            'name' => 'Tên',
            'contacts' => 'Contact',
            'orders' => 'Đơn',
            'revenue' => 'Doanh số Pushsale',
            'conversion_rate' => 'Tỉ lệ chốt',
        ],

        'empty' => [
            'title' => 'Chưa có dữ liệu phù hợp',
            'description' => 'Thử thay đổi bộ lọc thời gian hoặc từ khóa để xem kết quả.',
        ],

        'help' => [
            'title' => 'Công thức tính',
            'conversion_formula' => 'Tỉ lệ chốt = Số đơn / Contact x 100%.',
            'revenue_formula' => 'Doanh số lấy theo chế độ đã chọn: trước hoặc sau chiết khấu.',
            'pushsale_formula' => 'Doanh số Pushsale = Doanh số x Hệ số KPI của chuẩn Pushsale.',
            'telesale_attribution' => 'Telesale tính theo tương tác cuối cùng (last-touch) trước thời điểm chốt đơn.',
        ],
    ],
];
