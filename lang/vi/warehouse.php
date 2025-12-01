<?php

return [
    'label' => 'Kho',
    'form' => [
        'unit_warehouse' => 'Kho',
        'code' => 'Mã kho',
        'name' => 'Tên kho',
        'address' => 'Địa chỉ',
        'phone' => 'Số điện thoại',
        'note' => 'Ghi chú',
        'is_active' => 'Kích hoạt',
        'province' => 'Tỉnh/TP',
        'district' => 'Quận/Huyện',
        'ward' => 'Phường/Xã',
        'delivery_provinces' => 'Tỉnh/TP mặc định giao từ kho này',
        'manager' => 'Quản kho',
        'manager_phone' => 'Số ĐT quản kho',
        'sender_name' => 'Đăng đơn người gửi',
        'sender_info' => 'In đơn người gửi',
        'created_at' => 'Ngày tạo',
        'account_name' => 'Tài khoản GHN',
        'api_token' => 'API Token',
        'store_id' => 'Cửa hàng',
        'use_insurance' => 'Sử dụng bảo hiểm',
        'insurance_limit' => 'Giá trị bảo hiểm tối đa (VNĐ)',
        'required_note' => 'Lựa chọn xem hàng',
        'pickup_shift' => 'Ca lấy hàng',
        'cod_failed_amount' => 'Giao hàng thất bại thu tiền (VNĐ)',
        'fix_receiver_phone' => 'Cố định SĐT người nhận',
        'is_default' => 'Giao hàng bằng mặc định',
    ],
    'actions' => [
        'configure_shipping' => 'Cấu hình vận chuyển GHN',
    ],
    'navigation' => [
        'delivery' => 'Cấu hình giao hàng',
        'management' => 'Thông tin quản lý & Người gửi',
        'delivery_provinces' => 'Tỉnh/TP mặc định giao từ kho này',
        'manager' => 'Quản kho',
        'manager_phone' => 'Số ĐT quản kho',
        'sender_name' => 'Đăng đơn người gửi',
        'sender_info' => 'In đơn người gửi',
    ],
    'messages' => [
        'error' => [
            'load_stores' => 'Không thể tải danh sách cửa hàng',
            'reqired_token' => 'Vui lòng nhập API Token',
        ],
        'success' => [
            'load_stores' => 'Đã tải danh sách cửa hàng',
        ],
    ],
    'tooltip' => [
        'load_stores' => 'Tải danh sách cửa hàng',
    ],
];
