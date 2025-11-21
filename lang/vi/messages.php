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
        'error' => [
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
            'process_lead_failed' => 'Xử lý lead thất bại',
        ],
        'success' => [
            'connected' => 'Đã kết nối',
            'pages_synced' => 'Đã đồng bộ trang',
            'disconnected' => 'Đã ngắt kết nối',
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
