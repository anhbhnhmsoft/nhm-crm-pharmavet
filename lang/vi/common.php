<?php

return [
    'vietnam' => 'Tiếng Việt',
    'english' => 'Tiếng Anh',
    'laos' => 'Tiếng Lào',


    'success' => [
        'get_success' => 'Lấy dữ liệu thành công',
        'add_success' => 'Thêm dữ liệu thành công',
        'update_success' => 'Cập nhật dữ liệu thành công',
        'delete_success' => 'Xóa dữ liệu thành công',
    ],
    'error' => [
        'server_error' => 'Đã xảy ra lỗi trên máy chủ, vui lòng liên hệ quản trị viên để được hỗ trợ.',
        'request_error' => 'Không nhận được phản hồi từ máy chủ, vui lòng liên hệ quản trị viên để được hỗ trợ.',
        'program_error' => 'Đã xảy ra lỗi trên chương trình, vui lòng liên hệ quản trị viên để được hỗ trợ.',
        'unknown_error' => 'Đã xảy ra lỗi, vui lòng liên hệ quản trị viên để được hỗ trợ.',
        'invalid_or_expired_token' => 'Tài khoản của bạn đã hết hạn hoặc không hợp lệ. Vui lòng đăng nhập lại.',
        'api_not_found' => 'Nguồn không xác định đã xảy ra, vui lòng liên hệ quản trị viên để được hỗ trợ.',
        'method_not_allowed' => 'API không đúng định dạng, vui lòng liên hệ quản trị viên để được hỗ trợ.',
        'permission_error' => 'Bạn không có quyền làm tác vụ này, vui lòng liên hệ quản trị viên để được hỗ trợ.',
        'authorization_header_not_found' => 'Không tìm thấy tiêu đề ủy quyền, vui lòng liên hệ quản trị viên để được hỗ trợ.',
        'refresh_token_fail' => 'Tài khoản của bạn đã hết hạn hoặc không hợp lệ. Vui lòng đăng nhập lại.',
        'data_not_found' => 'Dữ liệu trống, vui lòng thử lại sau.',
        'data_not_fields' => 'Có một số dữ liệu chưa được điền, vui lòng thử lại.',
        'data_exists' => 'Dữ liệu đã tồn tại, vui lòng thử lại.',
        'validation_failed' => 'Dữ liệu không hợp lệ.',
        'max_content' => 'Nội dung không được vượt quá :max ký tự.',
    ],
    'table' => [
        'name' => 'Tên',
        'code' => 'Mã',
        'desc' => 'Miêu tả',
        'trashed' => 'Bản ghi bị xóa',
        'deleted_at' => 'Thời điểm xóa'
    ],
    'status' => [
        'label' => 'Trạng thái',
        'enabled' => 'Đang hoạt động',
        'disabled' => 'Vô hiệu hóa',
    ],
    'action' => [
        'view' => 'Xem',
        'edit' => 'Sửa',
        'delete' => 'Xóa',
        'restore' => 'Khôi phục',
        'force_delete' => 'Xóa vĩnh viễn',
        'confirm_delete' => 'Xác nhận xóa',
    ],
    'modal' => [
        'delete_title' => 'Xóa ',
        'delete_confirm' => 'Bạn có chắc chắn muốn xóa đã chọn?',
    ],
    'tooltip' => [
        'view' => 'Xem chi tiết ',
        'edit' => 'Chỉnh sửa ',
        'delete' => 'Xóa này',
        'restore' => 'Khôi phục đã xóa',
        'force_delete' => 'Xóa vĩnh viễn khỏi hệ thống',
    ],
];
