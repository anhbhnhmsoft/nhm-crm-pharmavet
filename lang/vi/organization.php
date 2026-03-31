<?php


return [
    'label' => 'tổ chức',
    'cluster_label' => 'Tổ chức',
    'table' => [
        'product_field' => 'Sản phẩm chính',
        'quantity_members' => 'Số lượng thành viên',
        'list_member' => 'Danh sách thành viên',
        'is_foreign' => 'Doanh nghiệp nước ngoài'
    ],
    'form' => [
        'general_info' => 'Thông tin chung',
        'general_info_desc' => 'Nhập thông tin cơ bản của tổ chức.',
        'name' => 'Tên tổ chức',
        'name_placeholder' => 'VD: Công ty TNHH ABC',
        'code' => 'Mã tổ chức',
        'code_placeholder' => 'VD: ABC123',
        'phone' => 'Số điện thoại',
        'phone_placeholder' => 'VD: 0901234567',
        'address' => 'Địa chỉ',
        'address_placeholder' => 'VD: 123 Đường A, Quận B',
        'product_field' => 'Lĩnh vực sản phẩm',
        'maximum_employees' => 'Số lượng tối đa thành viên',
        'description' => 'Mô tả',
        'description_placeholder' => 'Nhập mô tả ngắn gọn...',
        'disable' => 'Vô hiệu hóa tổ chức',
        'business_info' => 'Thông tin kinh doanh',
        'status' => 'Trạng thái',
        'disable_action' => 'Vô hiệu hóa',
        'enable' => 'Kích hoạt lại',
        'is_foreign' => 'Doanh nghiệp nước ngoài',
        'confirm_change' => 'Xác nhận thay đổi trạng thái',
        'disable_warning' => 'Việc vô hiệu hóa sẽ khóa quyền truy cập của tất cả thành viên vào hệ thống. Bạn có chắc chắn không?',
        'enable_warning' => 'Việc kích hoạt lại sẽ khôi phục quyền truy cập cho tất cả thành viên của tổ chức này. Bạn có chắc chắn không?',
        'code_auto_note' => 'Mã sẽ được tự động tạo từ tên, bỏ dấu và thay khoảng trắng bằng dấu gạch ngang, tối đa 20 ký tự.',
    ],
    'error' => [
        'unique_code' => 'Mã tổ chức đã tồn tại.',
        'invalid_code' => 'Mã tổ chức chỉ được chứa chữ HOA, số, gạch ngang, gạch dưới. Không dấu cách, (Mã phải bao gồm ít nhất 1 chữ số).',
    ],
];
