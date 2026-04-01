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
        'unknown_page' => 'Khác',
        'unknown_source' => 'Hệ thống',
        'view_mode' => 'Chế độ xem',
        'view_mode_full' => 'Đầy đủ',
        'view_mode_care' => 'Care Page',
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
            'team' => 'Team',
            'all_team' => 'Tất cả team',
            'staff' => 'Nhân sự',
            'all_staff' => 'Tất cả nhân sự',
            'pushsale_rule_set' => 'Chuẩn Pushsale',
            'all_pushsale' => 'Tất cả chuẩn',
            'scoring_rule_set' => 'Bộ quy đổi điểm',
            'all_scoring_rule_set' => 'Tất cả bộ điểm',
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
            'score' => 'Điểm',
            'total' => 'Tổng cộng',
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

    'budget' => [
        'navigation' => 'Ngân sách Marketing',
        'title' => 'Ngân sách & Chi tiêu Marketing',
        'filters' => [
            'from_date' => 'Từ ngày',
            'to_date' => 'Đến ngày',
            'channel' => 'Kênh',
            'campaign' => 'Chiến dịch',
        ],
        'form' => [
            'date' => 'Ngày',
            'channel' => 'Kênh',
            'campaign' => 'Chiến dịch',
            'budget_amount' => 'Ngân sách',
            'actual_spend' => 'Chi tiêu thực tế',
            'fee_amount' => 'Phí',
            'note' => 'Ghi chú',
            'attachment' => 'Chứng từ',
            'save' => 'Lưu ngân sách',
        ],
        'table' => [
            'date' => 'Ngày',
            'channel' => 'Kênh',
            'campaign' => 'Chiến dịch',
            'budget' => 'Ngân sách',
            'spend' => 'Chi tiêu',
            'fee' => 'Phí',
            'valid_leads' => 'Lead hợp lệ',
            'cost_per_lead' => 'Chi phí/lead',
            'new_revenue' => 'Doanh thu mới',
            'old_revenue' => 'Doanh thu cũ',
            'close_rate' => 'Tỉ lệ chốt',
            'cancel_rate' => '% hoàn/hủy',
            'aov' => 'AOV',
            'roi' => 'ROI',
            'status' => 'Trạng thái',
        ],
        'status' => [
            'ok' => 'Ổn định',
            'over_budget' => 'Vượt ngân sách',
            'roi_low' => 'ROI thấp',
        ],
        'attachment_history' => [
            'title' => 'Lịch sử chứng từ',
            'version' => 'Phiên bản',
            'file' => 'Đường dẫn file',
            'uploaded_by' => 'Người upload',
            'uploaded_at' => 'Thời điểm upload',
        ],
    ],

    'kpi' => [
        'navigation' => 'KPI Marketing',
        'title' => 'Dashboard KPI Marketing',
        'filters' => [
            'from_date' => 'Từ ngày',
            'to_date' => 'Đến ngày',
            'channel' => 'Kênh',
            'campaign' => 'Chiến dịch',
        ],
        'cards' => [
            'total_spend' => 'Tổng chi tiêu',
            'valid_leads' => 'Lead hợp lệ',
            'cost_per_lead' => 'Chi phí ra số',
            'new_revenue' => 'Doanh thu mới',
            'old_revenue' => 'Doanh thu cũ',
            'close_rate' => 'Tỉ lệ chốt',
            'cancel_rate' => '% hoàn/hủy',
            'aov' => 'AOV',
            'roi' => 'ROI',
        ],
        'variance' => [
            'up' => 'Tăng',
            'down' => 'Giảm',
            'flat' => 'Không đổi',
        ],
    ],

    'cancel_return' => [
        'navigation' => 'Phân tích hủy/hoàn',
        'title' => 'Phân tích lý do hủy/hoàn theo kênh',
        'filters' => [
            'from_date' => 'Từ ngày',
            'to_date' => 'Đến ngày',
            'source' => 'Nguồn',
            'source_detail' => 'Campaign/Chi tiết nguồn',
        ],
        'table' => [
            'source' => 'Nguồn',
            'source_detail' => 'Campaign/Chi tiết nguồn',
            'exception_type' => 'Loại ngoại lệ',
            'reason' => 'Lý do',
            'orders' => 'Số đơn',
            'ratio' => 'Tỉ lệ',
        ],
        'exception_type' => [
            'cancel' => 'Hủy',
            'return' => 'Hoàn',
            'exchange' => 'Đổi',
        ],
        'cards' => [
            'total_orders' => 'Tổng đơn',
            'cancel_orders' => 'Đơn hủy',
            'return_exchange_orders' => 'Đơn hoàn/đổi',
            'cancel_rate' => 'Tỷ lệ hủy',
            'exception_rate' => 'Tỷ lệ ngoại lệ',
            'junk_lead_rate' => 'Tỷ lệ lead rủi ro',
        ],
        'risky_campaigns' => [
            'title' => 'Campaign rủi ro cao',
            'campaign' => 'Campaign',
            'total_orders' => 'Tổng đơn',
            'risk_orders' => 'Đơn rủi ro',
            'risk_rate' => 'Tỷ lệ rủi ro',
        ],
    ],

    'alert_center' => [
        'navigation' => 'Trung tâm cảnh báo',
        'title' => 'Trung tâm cảnh báo Marketing',
        'filters' => [
            'status' => 'Trạng thái',
            'severity' => 'Mức độ',
            'alert_type' => 'Loại cảnh báo',
            'all_option' => 'Tất cả',
        ],
        'status' => [
            'open' => 'Chưa xử lý',
            'resolved' => 'Đã xử lý',
            'all' => 'Tất cả',
        ],
        'severity' => [
            'high' => 'Cao',
            'warning' => 'Cảnh báo',
        ],
        'alert_type' => [
            'over_budget' => 'Vượt ngân sách',
            'low_roi' => 'ROI thấp',
            'spend_without_lead' => 'Chi tiêu không có lead',
        ],
        'table' => [
            'alert_type' => 'Loại cảnh báo',
            'severity' => 'Mức độ',
            'channel' => 'Kênh',
            'campaign' => 'Campaign',
            'triggered_at' => 'Thời điểm phát sinh',
            'resolved_at' => 'Thời điểm xử lý',
            'actions' => 'Hành động',
        ],
        'actions' => [
            'resolve' => 'Đánh dấu đã xử lý',
            'reopen' => 'Mở lại',
        ],
    ],

    'common' => [
        'updated_success' => 'Cập nhật thành công',
        'no_data' => 'Không có dữ liệu phù hợp',
    ],
];
