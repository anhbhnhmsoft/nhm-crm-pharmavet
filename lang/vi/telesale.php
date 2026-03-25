<?php

return [
    // Telesale Operations Module
    'cluster_label' => 'Telesale',
    'navigation_label' => 'Danh sách data',
    'model_label' => 'Data',
    'plural_model_label' => 'Danh sách Data',
    'operation_page_title' => 'Tác nghiệp',
    'operation_page_label' => 'Màn hình tác nghiệp',
    'operation_navigation_label' => 'Tác nghiệp',

    // Table columns
    'table' => [
        'data_code' => 'Mã Data',
        'source' => 'Nguồn',
        'date_received' => 'Ngày về',
        'assigned_staff' => 'Sale phụ trách',
        'customer_name' => 'Tên khách hàng',
        'phone' => 'Số điện thoại',
        'status' => 'Trạng thái',
        'note' => 'Ghi chú',
        'next_action' => 'Tác nghiệp tiếp',
        'blacklist' => 'Danh sách đen',
        'unblacklist' => 'Xóa khỏi danh sách đen',
        'customer_type' => 'Phân loại data',
        'interaction_status' => 'Trạng thái tương tác',
        'organization' => 'Tổ chức',
    ],

    // Form sections
    'form' => [
        'customer_info' => 'Thông tin khách hàng',
        'customer_info_desc' => 'Thông tin cơ bản của khách hàng',
        'operation_result' => 'Kết quả tác nghiệp',
        'operation_result_desc' => 'Ghi nhận kết quả chăm sóc khách hàng',
        'order_entry' => 'Lên đơn hàng',
        'order_entry_desc' => 'Tạo đơn hàng cho khách hàng',
        'interaction_history' => 'Lịch sử tương tác',
        'interaction_history_desc' => 'Xem lịch sử cuộc gọi, tin nhắn',

        // Customer info fields
        'full_name' => 'Họ tên',
        'full_name_placeholder' => 'Nhập họ tên khách hàng',
        'phone_number' => 'Số điện thoại',
        'phone_placeholder' => '0901234567',
        'email' => 'Email',
        'email_placeholder' => 'email@example.com',
        'birthday' => 'Ngày sinh',
        'address' => 'Địa chỉ',
        'address_placeholder' => 'Nhập địa chỉ...',
        'organization' => 'Tổ chức',

        // Operation result fields
        'status_label' => 'Trạng thái',
        'feedback_note' => 'Ghi chú phản hồi',
        'feedback_placeholder' => 'Nhập ghi chú về cuộc gọi, phản hồi của khách...',
        'schedule_callback' => 'Hẹn lịch gọi lại',

        // Shipping info
        'shipping_info' => 'Thông tin giao hàng',
        'province' => 'Tỉnh/Thành',
        'district' => 'Quận/Huyện',
        'ward' => 'Phường/Xã',
        'detailed_address' => 'Địa chỉ chi tiết',
        'detailed_address_placeholder' => 'Số nhà, tên đường...',

        // Product & Payment
        'product_payment' => 'Sản phẩm & Thanh toán',
        'content' => 'Nội dung',
        'content_placeholder' => 'Nhập nội dung ghi chú...',
        'note_temp' => 'Ghi chú tạm',
        'product' => 'Sản phẩm chính',
        'product_list' => 'Danh sách sản phẩm',
        'quantity' => 'Số lượng',
        'unit_price' => 'Đơn giá',
        'cod_support_amount' => 'Hỗ trợ COD',
        'shipping_provider' => 'Nhà cung cấp giao hàng',
        'shipping_fee' => 'Phí giao hàng',
        'cod_fee' => 'Phí COD',
        'deposit' => 'Đặt cọc',
        'discount_amount' => 'Giảm giá',
        'total_amount' => 'Tổng tiền',
        'currency_suffix' => 'VNĐ',
        'ck1' => 'CK1 (%)',
        'ck2' => 'CK2 (%)',
        'operation_tabs' => 'Nghiệp vụ',
        'call_history' => 'Lịch sử tương tác',
        'add_new_note' => 'Thêm ghi chú',
        'interaction_type' => 'Loại tương tác',
        'result' => 'Kết quả',
    ],

    // Status options
    'status' => [
        'new' => 'Data mới',
        'processing' => 'Đang chăm sóc',
        'potential' => 'Tiềm năng (Nóng)',
        'unreachable' => 'Không liên lạc được',
        'closed' => 'Chốt đơn thành công',
        'cancelled' => 'Hủy / Từ chối',
    ],

    // Interaction types
    'interaction_type' => [
        'call' => 'Cuộc gọi',
        'sms' => 'Tin nhắn',
        'email' => 'Email',
        'note' => 'Ghi chú',
        'meeting' => 'Gặp mặt',
    ],

    // Interaction status
    'interaction_status' => [
        'completed' => 'Thành công',
        'missed' => 'Nhỡ cuộc gọi',
        'failed' => 'Thất bại',
    ],

    // Call direction
    'direction' => [
        'inbound' => 'Gọi đến',
        'outbound' => 'Gọi đi',
    ],

    // Shipping providers
    'shipping_provider' => [
        'ghn' => 'Giao Hàng Nhanh',
        'ghtk' => 'Giao Hàng Tiết Kiệm',
    ],

    // Actions
    'actions' => [
        'operation' => 'Tác nghiệp',
        'call' => 'Gọi',
        'assign_sale' => 'Gán Sale',
        'select_staff' => 'Chọn nhân viên',
        'add_data' => 'Nhập Data/Đơn mới',
        'blacklist' => 'Đưa vào danh sách đen',
        'unblacklist' => 'Xóa khỏi danh sách đen',
        'start_call' => 'Bắt đầu gọi',
        'hangup' => 'Tắt gọi',
        'customer_360' => 'Customer 360',
    ],

    // Filters
    'filters' => [
        'assigned_staff' => 'Sale phụ trách',
        'source' => 'Nguồn Data',
        'status' => 'Trạng thái',
        'date_received' => 'Ngày data về',
        'from_date' => 'Từ ngày',
        'to_date' => 'Đến ngày',
        'advanced_search' => 'Tìm kiếm nâng cao',
        'search_keyword' => 'Từ khóa',
        'search_keyword_placeholder' => 'Tên khách hàng / SĐT / Mã đơn',
        'customer_temperature' => 'Data nóng/lạnh',
        'care_result' => 'Kết quả chăm sóc',
        'duplicate_contact' => 'Contact trùng',
        'all' => 'Tất cả',
    ],

    // Source options
    'source' => [
        'facebook' => 'Facebook',
        'google' => 'Google',
        'zalo' => 'Zalo',
        'website' => 'Website',
        'manual' => 'Nhập tay',
    ],

    // Messages
    'messages' => [
        'no_interactions' => 'Chưa có lịch sử tương tác',
        'duration' => 'Thời lượng',
        'system' => 'Hệ thống',
        'unassigned' => 'Chưa phân công',
        'no_schedule' => 'Chưa hẹn lịch',
        'bulk_assign_success' => 'Đã gán Sale phụ trách cho các data đã chọn',
        'bulk_assign_log' => 'Gán hàng loạt data telesale cho Sale phụ trách',
        'new_lead_detected' => 'Có lead mới vừa được tạo',
        'new_lead_badge' => 'Lead mới',
        'clear_lead_badge' => 'Đã đọc thông báo lead mới',
        'duplicate_lead_warning' => 'Lead này trùng SĐT/Email với dữ liệu cũ',
        'duplicate_group_count' => 'Nhóm trùng: :count',
        'order_edit_locked_for_sale' => 'Sale không được sửa/chốt đơn khi đơn đã ở trạng thái giao hàng hoặc hoàn tất',
        'deposit_exceeds_total' => 'Tiền cọc không được lớn hơn tổng giá trị đơn hàng',
        'insufficient_stock' => 'Sản phẩm :product không đủ tồn kho tại kho đã chọn',
        'warehouse_required' => 'Vui lòng chọn kho xử lý đơn',

    ],

    // Order status
    'order_status' => [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'shipping' => 'Đang giao hàng',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
    ],
    'reason_interaction' => [
        'closing_order' => 'Chốt đơn',
        'no_answer' => 'Không trả lời',
        'busy' => 'Máy bận',
        'call_back' => 'Gọi lại',
        'subscribers' => 'Theo dõi',
        'think_more' => 'Cân nhắc',
        'no_need' => 'Không cần',
        'good_performance' => 'Hiệu quả',
        'poor_performance' => 'Không hiệu quả',
    ],
    'helper' => [
        'schedule_callback' => 'Hẹn lịch gọi lại',
        'auto_calculated_logistics_dimensions' => '* Trọng lượng và kích thước sẽ tự động tính toán dựa trên sản phẩm đã chọn.',
    ],

    'reports' => [
        'top_sale_navigation' => 'Top Sale Ranking',
        'legacy_suffix' => 'Legacy',
        'top_sale_title' => 'Báo cáo xếp hạng Sale',
        'funnel_navigation' => 'Phễu tác nghiệp',
        'funnel_title' => 'Báo cáo phễu chốt đơn',
        'generate' => 'Tạo báo cáo',
        'staff' => 'Nhân viên Sale',
        'all_staff' => 'Tất cả Sale',
        'step' => 'Bước tác nghiệp',
        'contacts' => 'Contacts',
        'orders' => 'Đơn chốt',
        'conversion_rate' => 'Tỉ lệ chốt',
        'revenue' => 'Doanh số',
        'new_customer' => 'Khách mới',
        'old_customer' => 'Khách cũ',
        'total_revenue' => 'Tổng doanh số',
        'total_orders' => 'Tổng đơn',
        'no_data' => 'Không có dữ liệu phù hợp bộ lọc',
        'export' => 'Xuất dữ liệu',
        'export_queued' => 'Đã tạo job export #:id',
        'export_completed' => 'Xuất báo cáo thành công',
        'export_failed' => 'Xuất báo cáo thất bại',
        'adjusted_revenue' => 'Doanh số (Pushsale)',
        'pushsale_rule_set' => 'Chuẩn Pushsale',
        'unlimited_close_date' => 'Không giới hạn ngày chốt',
        'sale_kpi_navigation' => 'Sale KPI',
        'sale_kpi_title' => 'Báo cáo KPI Sale',
        'month' => 'Tháng',
        'kpi_target' => 'Mục tiêu KPI',
        'kpi_progress' => 'Tiến độ KPI',
        'days_progress' => 'Tiến độ ngày trong tháng',
        'estimated_bonus' => 'Thưởng dự kiến',
        'estimated_income' => 'Tổng thu nhập dự kiến',
        'data_quality_navigation' => 'Báo cáo Data Sale',
        'data_quality_title' => 'Báo cáo chất lượng data',
        'total_contacts' => 'Tổng contact',
        'duplicate_contacts' => 'Contact trùng',
        'unique_contacts' => 'Contact không trùng',
        'call_metrics_navigation' => 'Call Metrics',
        'call_metrics_title' => 'Chỉ số gọi điện',
        'total_calls' => 'Tổng số cuộc gọi',
        'connected_calls' => 'Số cuộc nghe máy',
        'total_duration' => 'Tổng thời gian gọi (giây)',
        'avg_duration' => 'Thời gian gọi trung bình',
        'ceo_dashboard_navigation' => 'CEO Dashboard',
        'ceo_dashboard_title' => 'Dashboard CEO',
        'gross_revenue' => 'Doanh thu gộp',
        'net_revenue' => 'Doanh thu thuần',
    ],
    'customer360' => [
        'summary' => 'Tổng quan khách hàng',
        'timeline' => 'Lịch sử tác nghiệp',
        'total_revenue' => 'Tổng doanh thu',
        'debt_amount' => 'Công nợ',
        'total_orders' => 'Số đơn hàng',
        'latest_order_status' => 'Trạng thái đơn gần nhất',
    ],
];
