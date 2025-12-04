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
        'shipping_provider' => 'Nhà cung cấp giao hàng',
        'shipping_fee' => 'Phí giao hàng',
        'cod_fee' => 'Phí COD',
        'deposit' => 'Đặt cọc',
        'discount_amount' => 'Giảm giá',
        'total_amount' => 'Tổng tiền',
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
    ],

    // Filters
    'filters' => [
        'assigned_staff' => 'Sale phụ trách',
        'source' => 'Nguồn Data',
        'status' => 'Trạng thái',
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
    ],
];
