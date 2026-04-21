<?php
return [
    'organization' => [
        'error' => [
            'not_found' => 'Tổ chức không tồn tại!'
        ]
    ],
    'successfully' => [
        'saved' => 'Đã lưu',
    ],
    'integration' => [
        'error' => [
            'init_failed' => 'Khởi tạo kết nối thất bại!',
            'not_found' => 'Kết nối không tồn tại!',
        ]
    ],
    'meta_business' => [
        'connected_successfully' => 'Kết nối thành công!',
        'disconnected' => 'Đã ngắt kết nối',
        'pending_approval' => 'Đã kết nối Facebook và đang chờ quản trị viên duyệt.',
        'rejected' => 'Kết nối Facebook đã bị từ chối.',
        'error' => [
            'connect_failed' => 'Kết nối Facebook thất bại',
            'login_configuration_missing' => 'Thiếu cấu hình Facebook Login for Business. Vui lòng khai báo META_LOGIN_CONFIG_ID, META_REDIRECT và META_APP_ID.',
            'exchange_code_failed' => 'Không thể đổi mã xác thực Facebook Login for Business',
            'exchange_token_failed' => 'Không thể đổi token dài hạn',
            'callback_failed' => 'Callback thất bại',
            'no_user_token' => 'Không tìm thấy user token hợp lệ',
            'fetch_pages_failed' => 'Lấy danh sách trang thất bại: :error',
            'sync_pages_failed' => 'Đồng bộ trang thất bại',
            'fetch_lead_failed' => 'Lấy thông tin lead thất bại',
            'connection_test_failed' => 'Kiểm tra kết nối thất bại',
            'disconnect_failed' => 'Ngắt kết nối thất bại',
            'invalid_verify_token' => 'Verify Token không hợp lệ',
            'verify_token_error' => 'Lỗi xác thực token',
            'integration_not_found' => 'Không tìm thấy kết nối',
            'lookup_page_error' => 'Lỗi tìm kiếm trang',
            'page_entity_not_found' => 'Không tìm thấy thông tin trang',
            'page_token_not_found' => 'Không tìm thấy token trang',
            'page_token_expired' => 'Token của trang đã hết hạn',
            'webhook_subscribe_failed' => 'Không thể đăng ký webhook cho trang',
            'customer_blacklisted' => 'Khách hàng nằm trong danh sách đen',
            'process_lead_failed' => 'Xử lý lead thất bại',
            'approve_failed' => 'Duyệt kết nối Facebook thất bại',
            'reject_failed' => 'Từ chối kết nối Facebook thất bại',
            'store_lead_failed' => 'Lưu lead Facebook thất bại',
            'facebook_lead_not_found' => 'Không tìm thấy log lead Facebook',
            'invalid_signature' => 'Chữ ký webhook Facebook không hợp lệ',
        ],
        'success' => [
            'connected' => 'Đã kết nối',
            'pages_synced' => 'Đã đồng bộ trang',
            'disconnected' => 'Đã ngắt kết nối',
            'pending_approval' => 'Đã lưu kết nối Facebook, chờ quản trị viên duyệt',
            'pages_synced_pending' => 'Đã đồng bộ Pages và chuyển sang trạng thái chờ duyệt',
            'approved' => 'Đã duyệt kết nối Facebook thành công',
            'rejected' => 'Đã từ chối kết nối Facebook',
        ]
    ],
    'auth' => [
        'error' => [
            'logout_failed' => 'Đăng xuất thất bại: :error',
        ],
    ],
    'shipping' => [
        'error' => [
            'unknown' => 'Lỗi không xác định',
        ],
    ],
];
