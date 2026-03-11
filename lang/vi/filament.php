<?php

return [
    'navigation' => [
        'unit_administration' => 'Quản trị đơn vị',
        'unit_marketing' => 'Marketing',
        'unit_telesale' => 'Sale tác nghiệp',
        'unit_warehouse' => 'Kho',
        'unit_accounting' => 'Kế toán',
    ],
    'login' => [
        'organization_code' => 'Mã tổ chức',
        'username' => 'Tên đăng nhập',
        'password' => 'Mật khẩu',
        'remember_me' => 'Ghi nhớ đăng nhập',
        "error" => [
            'invalid_credentials' => 'Tên đăng nhập, mã tổ chức hoặc mật khẩu không đúng.',
            'activity_timeout' => 'Bạn đã không hoạt động quá 15 phút và đã bị đăng xuất tự động.'
        ]
    ],
    'organization' => [
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
            'code_auto_note' => 'Mã sẽ được tự động tạo từ tên, bỏ dấu và thay khoảng trắng bằng dấu gạch ngang.',
        ],
    ],
    'user' => [
        'label' => 'Thành viên',
        'plural' => 'Danh sách thành viên',
        'name' => 'Họ và tên',
        'username' => 'Tên đăng nhập',
        'email' => 'Email',
        'phone' => 'Số điện thoại',
        'role' => 'Vai trò',
        'position' => 'Chức vụ',
        'organization' => 'Tổ chức',
        'team' => 'Đội nhóm',
        'password' => 'Mật khẩu',
        'status' => 'Trạng thái',
        'disable' => 'Dừng hoạt động',
        'salary' => 'Mức lương',
        'online_hours' => 'Giờ online',
        'last_login' => 'Đăng nhập gần nhất',
        'last_logout' => 'Đăng xuất gần nhất',
        'basic_info' => 'Thông tin cơ bản',
        'account_info' => 'Thông tin tài khoản',
        'other_info' => 'Thông tin khác',
        'create' => 'Tạo thành viên',
        'edit' => 'Chỉnh sửa thành viên',
        'updated_at' => 'Thời điểm cập nhật',
        'updated_by' => 'Người cập nhật',
        'created_at' => 'Thời điểm tạo',
        'created_by' => 'Người tạo',
        'delete' => 'Xóa thành viên',
        'restore' => 'Khôi phục thành viên',
        'force_delete' => 'Xóa vĩnh viễn',
        'created_success' => 'Thêm thành viên thành công.',
        'updated_success' => 'Cập nhật thành viên thành công.',
        'deleted_success' => 'Xóa thành viên thành công.',
        'action' => [
            'impersonate_label' => 'Đăng nhập với tài khoản này',
            'impersonate_heading' => 'Xác nhận đăng nhập',
            'impersonate_description' => 'Bạn có chắc chắn muốn đăng nhập với vai trò người dùng này chứ ?',
            'choose_organize_first' => 'Chọn tổ chức trước',
            'choose_team' => 'Chọn nhóm'
        ],
        'exceed_members_limit' => 'Vượt giới hạn số lượng thành viên cho phép, vui lòng liên hệ quản trị viên để cập nhật!',
        'team' => 'Nhóm'
    ],
    'team' => [
        'label' => 'Đội nhóm',
        'name' => 'Tên nhóm',
        'organization' => 'Tổ chức',
        'code' => 'Mã',
        'type' => 'Kiểu nhóm',
        'description' => 'Miêu tả',
        'general_info' => 'Thông tin chung',
        'members' => 'Thành viên',
        'team_members' => 'Thành viên nhóm',
        'total_members' => 'Tổng số thành viên'
    ],
    'product' => [
        'organization' => 'Tổ chức',
        'organization_info' => 'Thông tin tổ chức',
        'label' => 'Sản phẩm',
        'created_success' => 'Thêm sản phẩm thành công.',
        'updated_success' => 'Cập nhật sản phẩm thành công.',
        'deleted_success' => 'Xóa sản phẩm thành công.',
        'cluster_label' => 'Sản phẩm & Combo',

        'product_info' => 'Thông tin sản phẩm',
        'enter_basic_product_infomation' => 'Nhập thông tin cơ bản của sản phẩm',
        'name' => 'Tên sản phẩm',
        'tooltip_name' => 'VD: Điện thoại Samsung Galaxy S23',
        'code_sku' => 'Mã SKU',
        'tooltip_sku' => 'VD: PROD-001',
        'tooltip_sku_place' => 'Mã định danh duy nhất cho sản phẩm',
        'barcode' => 'Mã vạch',
        'barcode_tooltip' => 'VD: 8934563210987',
        'unit' => 'Đơn vị tính',
        'unit_tooltip' => 'VD: Cái, Hộp, Kg',
        'weight' => 'Khối lượng (g)',
        'quantity' => 'Số lượng tồn kho',
        'description' => 'Mô tả sản phẩm',
        'description_detail' => 'Nhập mô tả chi tiết về sản phẩm...',
        'image' => 'Hình ảnh sản phẩm',
        'max_image' => 'Tải lên tối đa 1 hình ảnh',

        'price_and_tax' => 'Giá & Thuế',
        'setup_price_and_tax' => 'Thiết lập giá bán và thuế cho sản phẩm',
        'cost_price' => 'Giá nhập',
        'sale_price' => 'Giá bán',
        'vat_percent' => 'VAT (%)',
        'type_vat' => 'Loại VAT',
        'cost_must_be_less_than_sale' => 'Giá nhập không được lớn hơn giá bán',

        'dimension_and_quantity' => 'Kích thước & Số lượng',
        'dimension_and_quantity_info' => 'Thông tin về kích thước và số lượng tồn kho',
        'length' => 'Chiều dài (cm)',
        'width' => 'Chiều rộng (cm)',
        'height' => 'Chiều cao (cm)',
        'placeholder_dimension' => 'VD: 10',

        'attributes' => 'Thuộc tính sản phẩm',
        'attributes_info' => 'Thêm các thuộc tính như màu sắc, kích cỡ cho sản phẩm',
        'attribute_list' => 'Danh sách thuộc tính',
        'attribute_name' => 'Tên thuộc tính',
        'attribute_name_placeholder' => 'VD: Màu sắc, Kích thước',
        'attribute_value' => 'Giá trị',
        'attribute_value_placeholder' => 'VD: Đỏ, XL',
        'add_attribute' => 'Thêm thuộc tính mới',

        'assignments' => 'Phân công nhân sự',
        'assignments_info' => 'Gán người phụ trách sản phẩm',
        'sale_staff' => 'Nhân viên Kinh doanh (Sales)',
        'marketing_staff' => 'Nhân viên Marketing',
        'cskh_staff' => 'Nhân viên CSKH',
        'select_staff_placeholder' => 'Chọn nhân viên phụ trách',

        'category_and_brand' => 'Danh mục & Thương hiệu',
        'category_and_brand_info' => 'Phân loại sản phẩm theo danh mục và thương hiệu',
        'category' => 'Danh mục',
        'select_category_placeholder' => 'Chọn danh mục sản phẩm',
        'brand' => 'Thương hiệu',
        'select_brand_placeholder' => 'Chọn thương hiệu',
        'supplier' => 'Nhà cung cấp',
        'select_supplier_placeholder' => 'Chọn nhà cung cấp',
        'type' => 'Danh mục',

        'status' => 'Trạng thái',
        'config_status' => 'Cấu hình trạng thái hoạt động của sản phẩm',
        'business' => 'Trạng thái kinh doanh',
        'business_whene' => 'Tắt khi muốn tạm ngừng bán sản phẩm này',
        'has_attributes' => 'Có thuộc tính (biến thể)',
        'has_attributes_info' => 'Bật nếu sản phẩm có các biến thể như màu sắc, kích thước',
        'show_on_website' => 'Hiển thị trên website',
        'show_on_website_info' => 'Cho phép hiển thị sản phẩm trên website',
        'featured_product' => 'Sản phẩm nổi bật',
        'featured_product_info' => 'Đánh dấu là sản phẩm nổi bật',

        'generate_sku' => 'Tạo mã SKU tự động',
        'generate_barcode' => 'Tạo mã vạch tự động',
        'barcode_helper' => 'Mã vạch chuẩn EAN-13 (13 chữ số)',

        'no_vat_applied' => 'Không áp dụng VAT',
        'vat_inclusive_info' => 'Giá chưa VAT: :price ₫ | VAT: :vat ₫',
        'vat_exclusive_info' => 'VAT: :vat ₫ | Tổng giá: :total ₫',

        'sale_team' => 'Đội Sales',
        'marketing_team' => 'Đội Marketing',
        'cskh_team' => 'Đội CSKH',
        'select_team_first' => 'Chọn đội trước',
        'select_team_to_load_users' => 'Chọn đội để tải danh sách nhân viên',

        'import_hint' => 'Vui lòng tải tệp Excel theo đúng cấu trúc mẫu.
        Các cột bắt buộc: Tên sản phẩm, Mã SKU, Giá bán, VAT (%).
        Bạn có thể tải file mẫu để tham khảo.',
        'import_success' => 'Nhập dữ liệu sản phẩm thành công!',
        'import_failed' => 'Nhập dữ liệu thất bại. Vui lòng kiểm tra định dạng file.',

    ],
    'vat' => [
        'no' => 'Không chịu thuế',
        'inclusive' => 'giá đã bao gồm vat',
        'exclusive' => 'giá chưa bao gồm vat',
        'standard' => 'thuế xuất tiêu chuẩn 10%',
        'reduced' => 'giảm 5%',
        'zero_rate' => 'giảm 0%',
        'eight_percent' => 'giảm 8%',
        'desc' => [
            'no' => 'Sản phẩm không chịu thuế VAT (hàng xuất khẩu, sách giáo khoa...)',
            'inclusive' => 'Giá bán đã bao gồm thuế VAT trong đó',
            'exclusive' => 'Giá bán chưa bao gồm VAT, khách phải trả thêm VAT',
            'standard' => 'Áp dụng thuế VAT chuẩn 10% theo quy định',
            'reduced' => 'Áp dụng thuế VAT giảm 5%',
            'zero_rate' => 'Áp dụng thuế VAT 0% (cho hàng hóa, dịch vụ xuất khẩu)',
            'eight_percent' => 'Áp dụng thuế VAT giảm 8% (theo các chính sách giảm thuế tạm thời)',
        ],
    ],
    'shift' => [
        'label' => 'Ca làm việc',
        'table' => [
            'start_time' => 'giờ bắt đầu',
            'end_time' => 'giờ kết thúc',
        ],
        'sections' => [
            'basic_info' => 'Thông tin cơ bản',
            'basic_info_description' => 'Nhập thông tin cơ bản cho ca làm việc.',
            'user_assignment' => 'Phân công nhân viên',
            'user_assignment_description' => 'Chọn nhân viên tham gia ca làm việc.',
        ],
        'form' => [
            'assign_users' => 'Phân công người dùng',
        ],
        'validation' => [
            'start_equals_end' => 'Thời gian kết thúc không được trùng với thời gian bắt đầu',
            'end_before_start' => 'Thời gian kết thúc phải sau thời gian bắt đầu',
        ],
        'placeholder' => [
            'select_users' => 'Người dùng trong ca làm việc'
        ]
    ],
    'combo' => [
        'navigation_label' => 'Combo sản phẩm',
        'plural_label' => 'Combo',
        'label' => 'Combo',

        'basic_info' => 'Thông tin cơ bản',
        'basic_info_description' => 'Nhập thông tin cơ bản của combo',
        'name' => 'Tên combo',
        'name_placeholder' => 'VD: Combo Văn phòng tiết kiệm',
        'code' => 'Mã combo',
        'code_placeholder' => 'VD: CMB-001',
        'code_helper' => 'Mã định danh duy nhất cho combo',
        'generate_code' => 'Tạo mã tự động',
        'status' => 'Trạng thái',
        'status_helper' => 'Bật để kích hoạt combo này',
        'active' => 'Đang hoạt động',
        'inactive' => 'Không hoạt động',

        'time_period' => 'Thời gian áp dụng',
        'time_period_description' => 'Thiết lập thời gian hiệu lực của combo',
        'start_date' => 'Ngày bắt đầu',
        'end_date' => 'Ngày kết thúc',
        'products' => 'Sản phẩm trong combo',
        'products_description' => 'Chọn các sản phẩm và số lượng cho combo',
        'product_list' => 'Danh sách sản phẩm',
        'product' => 'Sản phẩm',
        'quantity' => 'Số lượng',
        'price_in_combo' => 'Giá trong combo',
        'price_helper' => 'Giá bán sản phẩm trong combo (có thể giảm giá)',
        'add_product' => 'Thêm sản phẩm',

        'summary' => 'Tổng quan',
        'summary_description' => 'Thông tin tổng hợp về combo',
        'total_product' => 'Số sản phẩm',
        'total_cost' => 'Tổng giá vốn',
        'total_combo_price' => 'Tổng giá bán',
        'discount' => 'Giảm giá',
        'items' => 'sản phẩm',
        'product_types' => 'Loại sản phẩm',

        'validity' => 'Hiệu lực',
        'validity_status' => 'Trạng thái hiệu lực',
        'valid' => 'Đang hiệu lực',
        'expired' => 'Đã hết hạn',
        'upcoming' => 'Sắp diễn ra',
        'all' => 'Tất cả',

        'date_range' => 'Khoảng thời gian',
        'from' => 'Từ ngày',
        'to' => 'Đến ngày',
        'deleted_records' => 'Bản ghi đã xóa',

        'created_by' => 'Người tạo',
        'created_at' => 'Ngày tạo',
        'updated_by' => 'Người cập nhật',
        'updated_at' => 'Ngày cập nhật',

        'created_successfully' => 'Tạo combo thành công',
        'updated_successfully' => 'Cập nhật combo thành công',
        'deleted_successfully' => 'Xóa combo thành công',
    ],
    'shipping' => [
        // Navigation & Title
        'navigation_label' => 'Cấu hình vận chuyển',
        'title' => 'Cấu hình vận chuyển GHN',

        // Connection Info
        'connection_info' => 'Thông tin kết nối',
        'connection_info_description' => 'Nhập thông tin tài khoản GHN để kết nối',
        'account_name' => 'Tên tài khoản',
        'account_name_placeholder' => 'VD: Cửa hàng ABC',
        'account_name_helper' => 'Tên tài khoản GHN của bạn',
        'api_token' => 'API Token',
        'api_token_placeholder' => 'Nhập token từ GHN',
        'api_token_helper' => 'Token API lấy từ trang quản trị GHN',
        'connection_status' => 'Trạng thái kết nối',
        'connected' => 'Đã kết nối',
        'not_connected' => 'Chưa kết nối',
        'test_connection' => 'Kiểm tra kết nối',
        'allow_cod_on_failed' => 'cho phép thu phí khi giao thất bại',
        'required_note' => 'Ghi chú bắt buộc',
        'allow_to_try' => 'Cho phép thử',
        'allow_viewing_not_trial' => 'Cho phép xem không cho phép thử',
        'no_viewing' => 'không cho phép xem',
        // Shop Info
        'shop_info' => 'Thông tin cửa hàng',
        'shop_info_description' => 'Chọn cửa hàng mặc định cho vận chuyển',
        'default_store' => 'Cửa hàng mặc định',
        'select_store' => 'Chọn cửa hàng',
        'test_connection_first' => 'Vui lòng kiểm tra kết nối trước',
        'test_connection_to_load_stores' => 'Nhấn nút "Kiểm tra kết nối" để tải danh sách cửa hàng',
        'store_helper' => 'Cửa hàng này sẽ được dùng làm điểm lấy hàng mặc định',

        // Insurance Settings
        'insurance_settings' => 'Cài đặt bảo hiểm',
        'insurance_settings_description' => 'Thiết lập bảo hiểm hàng hóa',
        'use_insurance' => 'Sử dụng bảo hiểm',
        'use_insurance_helper' => 'Bật để mua bảo hiểm cho đơn hàng',
        'insurance_limit' => 'Hạn mức bảo hiểm',
        'insurance_limit_helper' => 'Giá trị tối đa được bảo hiểm cho mỗi đơn hàng',

        // Delivery Settings
        'delivery_settings' => 'Cài đặt giao hàng',
        'delivery_settings_description' => 'Thiết lập quy trình giao hàng',
        'allow_view_goods' => 'Cho phép xem hàng',
        'allow_view_goods_helper' => 'Người nhận được xem hàng trước khi thanh toán',
        'allow_code_on_failed' => 'Cho phép thử lại khi thất bại',
        'allow_code_on_failed_helper' => 'Cho phép giao lại khi giao hàng thất bại lần đầu',
        'default_pickup_shift' => 'Ca lấy hàng mặc định',
        'morning_shift' => 'Ca sáng (8h-12h)',
        'afternoon_shift' => 'Ca chiều (13h-18h)',
        'evening_shift' => 'Ca tối (18h-21h)',
        'pickup_shift_helper' => 'Ca lấy hàng mặc định cho đơn hàng mới',
        'default_pickup_time' => 'Giờ lấy hàng mặc định',
        'pickup_time_helper' => 'Giờ lấy hàng ưu tiên (tùy chọn)',

        // Messages
        'connecting' => 'Đang kết nối...',
        'connection_success' => 'Kết nối thành công',
        'connection_failed' => 'Kết nối thất bại',
        'connection_error' => 'Lỗi kết nối',
        'api_error' => 'Lỗi API',
        'no_shops_found' => 'Không tìm thấy cửa hàng nào',
        'found_shops' => 'Đã tìm thấy :count cửa hàng',
        'validation_error' => 'Lỗi xác thực',
        'saved_successfully' => 'Lưu cấu hình thành công',
        'save_error' => 'Lỗi khi lưu cấu hình',
        'save' => 'Lưu cấu hình',
        'saving' => 'Đang lưu...',

        // Connection Info
        'account_name_placeholder' => 'VD: Cửa hàng ABC',
        'account_name_helper' => 'Tên tài khoản GHN của bạn',

        // Connected Stores
        'connected_stores' => 'Danh sách cửa hàng đã kết nối',
        'store_id' => 'Mã cửa hàng',
        'phone' => 'Số điện thoại',
        'address' => 'Địa chỉ',
        'default' => 'Mặc định',

        // Help Section
        'help' => 'Hướng dẫn',
        'how_to_get_token' => 'Cách lấy API Token từ GHN',
        'help_step_1' => 'Đăng nhập vào tài khoản GHN tại https://5sao.ghn.dev',
        'help_step_2' => 'Vào menu "Cài đặt" → "Tài khoản"',
        'help_step_3' => 'Tìm mục "API Token" và nhấn "Tạo token mới" nếu chưa có',
        'help_step_4' => 'Copy token và paste vào ô "API Token" ở trên',
        'note' => 'Lưu ý quan trọng',
        'note_1' => 'Token API rất quan trọng, không chia sẻ với người khác',
        'note_2' => 'Mỗi tổ chức chỉ được cấu hình một tài khoản GHN',
        'note_3' => 'Bạn cần có ít nhất một cửa hàng đã được kích hoạt trên GHN',
        'token_required' => 'Token API không được để trống',
        'no_config_found' => 'Không tìm thấy cấu hình GHN',
    ],
    'lead' => [
        'label' => 'Cấu hình phân bổ ',
        'guide' => [
            'title' => 'Hướng dẫn sử dụng',
            'customer_types' => [
                'title' => 'Các loại khách hàng (Customer Types)',
                'b2c' => 'Khách hàng cá nhân (B2C)',
                'b2c_desc' => 'Dành cho các số điện thoại cá nhân, không thuộc doanh nghiệp.',
                'b2b' => 'Khách hàng doanh nghiệp (B2B)',
                'b2b_desc' => 'Dành cho các số điện thoại thuộc về doanh nghiệp, tổ chức.',
            ],
            'distribution_methods' => [
                'title' => 'Các phương thức phân phối',
                'round_robin' => 'Luân phiên (Round-Robin)',
                'round_robin_desc' => 'Phân phối lần lượt cho từng nhân viên theo thứ tự (1, 2, 3, 1, 2, 3...).',
                'load_balancing' => 'Cân bằng tải (Load-Balancing)',
                'load_balancing_desc' => 'Phân phối cho nhân viên có tải thấp nhất (ít lead nhất) sau khi cân nhắc trọng số.',
            ],
            'staff_weight' => [
                'title' => 'Trọng số nhân viên (Staff Weight)',
                'content' => 'Trọng số (Weight) là độ ưu tiên của nhân viên. Nhân viên có trọng số cao hơn sẽ được phân bổ nhiều lead hơn. Mặc định là 1.',
            ],
            'example' => [
                'title' => 'Ví dụ minh họa',
                'content' => 'Quy tắc 1: Nếu là loại khách hàng B2C, áp dụng phương thức Luân phiên cho nhân viên SALE. Quy tắc 2: Nếu là loại khách hàng B2B, áp dụng phương thức Cân bằng tải cho nhân viên CSKH.',
            ],
        ],
        'config' => [
            'notifications' => [
                'saved' => [
                    'title' => 'Lưu cấu hình thành công',
                    'body' => 'Cấu hình phân phối lead đã được cập nhật.',
                ],
                'error' => [
                    'title' => 'Lỗi khi lưu cấu hình',
                ],
            ],
            'general_info' => 'Thông tin chung',
            'general_info_desc' => 'Thiết lập tên cấu hình và sản phẩm áp dụng.',
            'name' => 'Tên cấu hình',
            'name_placeholder' => 'VD: Cấu hình phân phối Lead Mùa Hè 2025',
            'product' => 'Sản phẩm áp dụng',
            'product_placeholder' => 'Chọn sản phẩm (Để trống áp dụng cho tất cả)',
            'staff_list' => 'Danh sách nhân viên',
            'staff_list_desc' => 'Thêm nhân viên được phân bổ và đặt trọng số.',
            'rules' => 'Quy tắc phân phối Lead',
            'rules_desc' => 'Đặt các quy tắc cho từng loại khách hàng và loại nhân viên.',
        ],
        'table' => [
            'name' => 'Tên cấu hình',
            'product' => 'Sản phẩm',
            'created_by' => 'Người tạo',
            'created_at' => 'Ngày tạo',
            'updated_at' => 'Ngày cập nhật',
            'deleted_at' => 'Ngày xóa',
        ],
        'filter' => [
            'organization' => 'Lọc theo tổ chức',
            'product' => 'Lọc theo sản phẩm',
            'trashed' => 'Bản ghi đã xóa',
        ],
        'action' => [
            'view' => 'Xem',
            'edit' => 'Sửa',
            'delete' => 'Xóa',
            'force_delete' => 'Xóa vĩnh viễn',
            'restore' => 'Khôi phục',
        ],
        'empty' => [
            'heading' => 'Chưa có cấu hình phân phối',
            'description' => 'Tạo cấu hình đầu tiên để bắt đầu phân phối lead',
        ],
        'rule' => [
            'title' => 'Quy tắc',
            'label' => 'Danh sách quy tắc',
            'field' => [
                'customer_type' => 'Loại số',
                'staff_type' => 'Loại nhân viên',
                'distribution_method' => 'Phương thức phân phối',
                'distribution_method_helper' => 'Chọn cách thức phân phối lead cho nhân viên',
            ],
            'action' => [
                'add' => 'Thêm quy tắc',
                'delete' => 'Xóa quy tắc',
            ],
            'item' => [
                'untitled' => 'Quy tắc chưa có tiêu đề',
                'new' => 'Quy tắc mới',
            ],
        ],
        'staff' => [
            'title' => 'Nhân viên',
            'label' => 'Danh sách nhân viên',
            'type' => 'Loại nhóm',
            'sale_title' => 'Nhân viên sale',
            'sale_label' => 'Danh sách nhân viên sale',
            'weight' => 'Định mức',
            'cskh_title' => 'Nhân viên CSKH',
            'cskh_label' => 'Danh sách nhân viên CSKH'
        ],
        'customer' => [
            'new' => 'Số mới',
            'new_duplicate' => 'Số mới trùng',
            'old_customer' => 'Số cũ',
            'label' => 'Loại data'
        ],
        'distribution' => [
            'by_definition' => 'Theo định mức',
            'most_recent_repicient' => 'Người nhận số gần nhất',
            'label' => 'Phương thức'
        ]
    ],
    'integration' => [
        'navigation_label' => 'Kết nối Marketing',
        'model_label' => 'Kết nối',
        'plural_model_label' => 'Kết nối Marketing',

        // Sections
        'sections' => [
            'basic_info' => [
                'title' => 'Thông tin cơ bản',
                'description' => 'Chọn loại kết nối và đặt tên',
            ],
            'facebook_login' => [
                'title' => 'Kết nối Facebook',
                'description' => 'Đăng nhập Facebook để tự động đồng bộ lead',
            ],
            'webhook' => [
                'title' => 'Cấu hình Webhook',
                'description' => 'Cấu hình webhook để nhận dữ liệu từ Landing Page hoặc Website',
            ],
            'field_mapping' => [
                'title' => 'Ánh xạ trường dữ liệu',
                'description' => 'Cấu hình cách map field từ nguồn sang hệ thống',
            ],
            'facebook_connected' => 'Đã kết nối Facebook thành công',
            'facebook_connect_required' => 'Vui lòng đăng nhập Facebook để bắt đầu nhận lead',
            'facebook_popup_hint' => 'Cửa sổ đăng nhập Facebook sẽ mở trong popup',
            'connecting' => 'Đang kết nối...',
            'disconnect_confirm' => 'Bạn có chắc chắn muốn ngắt kết nối Facebook? Tất cả Pages sẽ bị hủy đăng ký.',
            'facebook_connected_summary' => 'Đã kết nối :count Pages. Đồng bộ lần cuối: :last_sync',
            'never_synced' => 'Chưa bao giờ',
            'pages' => 'Pages',
            'last_sync' => 'Đồng bộ lần cuối',
        ],
        'defaults' => [
            'facebook_name' => 'Facebook Lead Ads',
            'landing_page_name' => 'Landing Page',
            'website_name' => 'Website',
        ],
        // Fields
        'fields' => [
            'type' => 'Loại kết nối',
            'name' => 'Tên cấu hình',
            'name_placeholder' => 'VD: Facebook Ads Q4 2024',
            'status' => 'Trạng thái',
            'connection_status' => 'Trạng thái kết nối',
            'connected_pages' => 'Danh sách Pages đã kết nối',
            'page_name' => 'Tên Page',
            'page_id' => 'Page ID',
            'page' => 'Page',
            'default_product' => 'Sản phẩm mặc định',
            'default_product_helper' => 'Lead từ nguồn này sẽ được gán vào sản phẩm này',
            'active' => 'Kích hoạt',
            'webhook_url' => 'URL Webhook',
            'webhook_url_helper' => 'URL nhận webhook từ landing page/website',
            'webhook_secret' => 'Webhook Secret',
            'webhook_secret_helper' => 'Secret key để xác thực webhook',
            'webhook_secret_locked' => 'Secret key đã được tạo (chỉ xem)',
            'field_mapping' => 'Ánh xạ trường',
            'field_mapping_key' => 'Trường trong hệ thống',
            'field_mapping_value' => 'Trường từ nguồn',
            'field_mapping_helper' => 'VD: name => full_name, phone => phone_number',
            'field_mapping_add' => 'Thêm mapping',
        ],

        // Defaults
        'defaults' => [
            'facebook_name' => 'Facebook Lead Ads',
            'landing_page_name' => 'Landing Page',
            'website_name' => 'Website',
        ],

        // Actions
        'actions' => [
            'create' => 'Tạo kết nối mới',
            'connect_facebook' => 'Đăng nhập Facebook',
            'sync_pages' => 'Đồng bộ Pages',
            'disconnect' => 'Ngắt kết nối',
        ],

        // Status
        'status' => [
            'pending' => 'Chờ kết nối',
            'connected' => 'Đã kết nối',
            'expired' => 'Hết hạn',
            'error' => 'Lỗi',
            'not_connected' => 'Chưa kết nối',
        ],

        // Table
        'table' => [
            'name' => 'Tên',
            'type' => 'Loại',
            'status' => 'Trạng thái',
            'pages' => 'Pages',
            'last_sync' => 'Đồng bộ cuối',
            'never' => 'Chưa bao giờ',
            'created_at' => 'Ngày tạo',
        ],

        // Filters
        'filters' => [
            'type' => 'Loại kết nối',
            'status' => 'Trạng thái',
        ],

        'notifications' => [
            'connected' => [
                'title' => 'Kết nối thành công',
                'body' => 'Facebook đã được kết nối và đồng bộ Pages',
            ],
            'sync_success' => [
                'title' => 'Đồng bộ thành công',
                'body' => 'Đã đồng bộ :count Pages từ Facebook',
            ],
            'sync_error' => [
                'title' => 'Lỗi đồng bộ',
            ],
            'disconnected' => [
                'title' => 'Đã ngắt kết nối',
            ],
            'popup_blocked' => [
                'title' => 'Popup bị chặn',
                'body' => 'Vui lòng cho phép popup từ website này để đăng nhập Facebook',
            ],
            'cancelled' => [
                'title' => 'Đã hủy',
            ],
            'error' => [
                'title' => 'Lỗi',
                'body' => 'Đã có lỗi xảy ra',
            ],
        ],

        // OAuth Callback Pages
        'oauth' => [
            'success_title' => 'Kết nối thành công',
            'success_heading' => 'Kết nối Facebook thành công!',
            'success_message' => 'Pages của bạn đã được đồng bộ. Cửa sổ này sẽ tự động đóng...',
            'error_title' => 'Kết nối thất bại',
            'error_heading' => 'Không thể kết nối Facebook',
            'error_message' => 'Đã xảy ra lỗi khi kết nối với Facebook',
            'unknown_error' => 'Lỗi không xác định',
            'close_window' => 'Đóng cửa sổ',
        ],

        'api' => [
            'sync_success' => 'Đã đồng bộ :count Pages thành công',
            'disconnected' => 'Đã ngắt kết nối thành công',
        ],

        'success' => [
            'facebook_connected' => 'Kết nối Facebook thành công!',
        ],
        'errors' => [
            'no_integration_found' => 'Không tìm thấy integration',
            'integration_not_found' => 'Integration không tồn tại',
            'connection_failed' => 'Kết nối thất bại',
        ],
    ],

    'services' => [
        'meta_business' => [
            'connected_successfully' => 'Đã kết nối thành công với Facebook',
            'disconnected' => 'Đã ngắt kết nối',
        ],
    ],

    'meta_business' => [
        'connected_successfully' => 'Đã kết nối thành công với Facebook',
        'disconnected' => 'Đã ngắt kết nối',
    ],
    'marketing' => [
        'cluster_label' => ''
    ]
];
