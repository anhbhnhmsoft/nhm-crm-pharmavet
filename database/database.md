-- Database Schema Documentation

# provinces

    # note
    - bảng lưu trữ thông tin các tỉnh/thành phố

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - code (varchar(2), not null, unique)
    - name: (varchar(5), not null)
    - code_name: (varchar(20), not null, unique)
    - division_type: (varchar(50), not null)
    - metadata: (json, nullable)
    - timestamps

# districts

    # note
    - bảng lưu trữ thông tin các quận/huyện

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - code: (char(5), not null, indexed)
    - name: (varchar(100), not null)
    - code_name: (varchar(150), not null)
    - division_type: (varchar(100), not null)
    - province_id: (unsignedBigInteger, not null, foreign key → provinces.id) -- tỉnh/thành phố sở hữu quận/huyện
    - province_code: (char(2), nullable, indexed) -- mã tỉnh để tham chiếu API
    - metadata: (json, nullable)
    - timestamps

# wards

    # note
    - bảng lưu trữ thông tin các phường/xã

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - code: (char(5), not null, indexed) -- mã từ API
    - name: (varchar(100), not null)
    - code_name: (varchar(150), not null)
    - division_type: (varchar(100), not null)
    - district_id: (unsignedBigInteger, not null, foreign key → districts.id) -- quận/huyện sở hữu phường/xã
    - district_code: (char(5), nullable, indexed) -- mã quận để tham chiếu API
    - metadata: (json, nullable)
    - timestamps

# organizations

    # note
    - bảng lưu thông tin các tổ chức bao gồm tên, địa chỉ, thông tin liên hệ, v.v.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - name: (varchar(255), not null)
    - code: (varchar(20), not null, unique)
    - phone: (varchar(20), nullable)
    - address: (varchar(255), nullable)
    - product_field: (smallint, not null) -- lĩnh vực sản phẩm, enum ProductField
    - description: (text, nullable)
    - disable: (boolean, default false) -- trạng thái vô hiệu hóa
    - maximum_employees: (unsignedInteger, default 99) -- số lượng tối đa của tổ chức
    - softDeletes
    - timestamps

# bảng teams

    # note
    - bảng lưu đội nhóm trong mỗi tổ chức

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - name: (varchar(255), not null ) -- tên đội nhóm
    - organization_id: (unsignedBigInteger, nullable) -- tổ chức sở hữu nhóm
    - code: (string(20), unique) -- mã đội nhóm
    - type: (tiny integer) -- loại đội nhóm
    - description: (text, nullable ) -- mô tả
    - softDeletes
    - timestamps

# bảng users

    # note
    - bảng lưu thông tin người dùng bao gồm thông tin cá nhân, quyền truy cập, v.v.

    # cấu trúc
    - id : (int, primary key, auto-increment)
    - organization_id : (int, foreign key -> organizations.id, not null) -- tổ chức người dùng thuộc về
    - username : (varchar(100), not null, unique)
    - password : (varchar(255), not null) -- mật khẩu hash
    - email : (varchar(255), nullable, unique)
    - name : (varchar(255), not null)
    - phone : (varchar(20), nullable)
    - role : (smallint, not null) -- vai trò người dùng, enum UserRole
    - position : (smallint, nullable) -- chức vụ người dùng, enum UserPosition
    - salary : (decimal(15, 2), nullable) -- lương người dùng
    - disable : (boolean, default false) -- trạng thái vô hiệu hóa
    - online_hours: (decimal(15, 2), nullable ) -- tổng số giờ online
    - last_login_at: (timestamp, nullable) -- giờ đăng nhập gần nhất
    - last_logout_at: (timestamp, nullable) -- giờ đăng xuất gần nhất
    - team_id : (unsignedBigInteger, nullable) -- đội nhóm thuộc người dùng thuộc về
    - updated_by : (unsignedBigInteger, nullable) -- người cập nhật cuối cùng
    - created_by : (unsignedBigInteger, nullable) -- người tạo mới
    - softDeletes
    - timestamps

# bảng user_team

    # note
    - bảng lưu thông tin người dùng trong đội nhóm

    # cấu trúc
    - id : (int, primary key, auto-increment)
    - user_id : (int, foreign key -> users.id, not null) -- người dùng thuộc về
    - team_id : (int, foreign key -> teams.id, not null) -- đội nhóm thuộc về
    - timestamps

# bảng user_logs

    # note
    - bảng lưu lịch sử hoạt động của người dùng bao gồm đăng nhập, đăng xuất, v.v.

    # cấu trúc
    - id : (int, primary key, auto-increment)
    - user_id : (int, foreign key -> users.id, not null) -- người dùng thực hiện hành động
    - desc : (text, not null) -- hành động thực hiện
    - ip_address : (varchar(255), nullable) -- địa chỉ IP của người dùng
    - timestamps

# bảng products

    # note
    - Bảng chính lưu trữ thông tin cơ bản và chi tiết của tất cả các sản phẩm v.v.

    # cấu trúc
    - id : (int, primary key, auto-increment)
    - organization_id: (unsignedBigInteger, nullable) -- tổ chức sở hữu sản phẩm
    - name : (varchar(255), Not Null) -- tên sản phẩm gốc (Tên SP gốc).
    - sku : (varchar(100), unique, not null) -- mã SKU sản phẩm, dùng để định danh duy nhất
    - unit : (varchar(50), nullable) -- đơn vị tính của sản phẩm (vd: cái, hộp, kg)
    - weight : (unsigned int, not null ) -- khối lượng sản phẩm
    - cost_price : (decimal(15, 2), nullable) -- Giá nhập/Giá vốn của sản phấm
    - sale_price : (decimal(15, 2), nullable) -- Giá bán niêm yết của sản phẩm.
    - image : (varchar(255), nullable) -- hình ảnh sản phẩm
    - description : (text, nullable) -- miêu tả sản phẩm
    - barcode : (varchar(100), nullable) -- mã vạch sản phẩm
    - type : (unsigned tiny integer, not null) -- Loại sản phẩm
    - length : (varchar(50), nullable) -- chiều dài
    - height : (varchar(50), nullable) -- chiều cao
    - width : (varchar(50), nullable) -- chiều rộng
    - quantity : (unsigned integer, not null) -- số lượng sản phẩm
    - vat_rate : (unsigned tiny int, default 0) -- thuế VAT (%) áp dụng cho sản phẩm (0, 5, 10)
    - is_business_product : (boolean, default false) -- đánh dấu sản phẩm đã ngừng kinh doanh.
    - has_attributes  : (boolean, default false) -- cờ xác định sản phẩm có biến thể/thuộc tính hay không.
    - softDeletes
    - timestamps

# bảng product_attributes

    # note
    - bảng lưu trữ các thuộc tính (biến thể) cụ thể của từng sản phẩm. Cấu trúc này giả định các thuộc tính là độc lập cho từng sản phẩm (ví dụ: "Màu sắc: Đỏ" của Sản phẩm A)

    # cấu trúc
    - id : (int, primary key, auto-increment)
    - product_id : (int, foreign key -> products.id, not null) -- sản phẩm sở hữu thuộc tính
    - name : (varchar(100), not null) -- tên của thuộc tính (ví dụ: Màu sắc, Kích cỡ)
    - value : (varchar(100), not null) --  Giá trị của thuộc tính (ví dụ: Đỏ, XL)
    - product_id, name index
    - softDeletes
    - timestamps

# bảng product_user_assignments

    # note
    - bảng pivot lưu trữ việc gán sản phẩm cụ thể cho từng nhân viên/người dùng theo vai trò. Bảng này gộp logic của các bảng pivot Marketing, Sale, CSKH vào làm một

    # cấu trúc
    - id : (int, primary key, auto-increment)
    - user_id  : (int, foreign key -> users.id, not null) -- nhân viên được gán (người dùng thực hiện công việc liên quan đến sản phẩm này)
    - type : (unsigned tiny int, not null) -- xác định vai trò của nhân viên đối với sản phẩm (1: SALE, 2: CSKH, 3: MARKETING, 4: BILL_OF_LADING)
    - product_id : (int, foreign key -> products.id, not null) -- sản phẩm được gán cho người dùng
    - (product_id, user_id, type) : (unique index) -- đảm bảo một nhân viên chỉ được gán một vai trò duy nhất cho một sản phẩm
    - timestamps

# bảng shifts

    # note
    - bảng lưu ca làm việc của người việc.
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - name : (varchar(255), not null) -- tên ca làm việc
    - organization_id : (int, foreign key -> organizations.id, not null) -- tổ chức của ca làm việc
    - start_time : (time, not null) -- giờ bắt đầu ca
    - end_time : (time, not null) -- giờ kết thúc ca
    - softDeletes
    - timestamps

# bảng user_shift

    # note
    - bảng lưu người dùng trong ca làm việc
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - user_id : (int, foreign key -> users.id, not null) -- người dùng có trong ca làm việc
    - shift_id : (int, foreign key -> shifts.id, not null) -- ca làm việc của người dùng
    - unique[user_id, shift_id]
    - softDeletes
    - timestamps

# bảng combos

    # note
    - bảng lưu combo sản phẩm.
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - code : (varchar(50), unique, not null) -- mã combo duy nhất
    - name : (varchar(255), not null) -- tên combo sản phẩm
    - total_product : (unsigned int, default 0) -- tổng số sản phẩm combo
    - total_cost : (decimal(15,2), default 0) -- tổng giá sản phẩm gốc
    - total_combo_price : (decimal(15,2), default 0) -- tổng giá sản phẩm combo
    - status : (tiny integer, not null) -- trạng thái sản phẩm
    - start_date : (timestamp, nullable) -- ngày bắt đầu áp dụng combo
    - end_date : (timestamp, nullable) -- ngày kết thúc áp dụng combo
    - created_by : (unsignedBigInteger, nullable) -- người tạo mới
    - updated_by : (unsignedBigInteger, nullable) -- người cập nhật cuối cùng
    - softDeletes
    - timestamps

# bảng combo_product

    # note
    - bảng pivot giữa combo và sản phẩm
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - combo_id : (int, foreign key -> combos.id, not null) -- combo sản phẩm
    - product_id : (int, foreign key -> products.id, not null) -- sản phẩm liên quan
    - quantity : (unsigned integer, default 1) -- số lượng sản phẩm
    - price : (decimal(15,2), default 0) -- giá sản phẩm trong combo
    - timestamps
    - unique[combo_id, product_id]

# bảng shipping_configs

    # note
    - bảng lưu trữ cấu hình giao hàng của tổ chức
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id : (int, foreign key  -> organizations.id, not null) -- tổ chức có cấu hình
    - account_name : (varchar(255), not null) -- tài khoản ghn mà tổ chức liên kết giao hàng
    - api_token : (text, not null, encrypted ) -- token để gọi api đến hệ thống ghn
    - default_store_id : (varchar(255), nullable) -- id cửa hàng của tài khoản đã liên kết
    - use_insurance : (boolean, default false) -- cấu hình mặc định khi tạo đơn có sử dụng bảo hiểm đơn hàng không
    - insurance_limit : (decimal(15,2), nullable) -- giá trị bảo hiểm hàng hóa khai báo khi lên đơn
    - required_note : (tiny integer, not null) -- yêu cầu note khi người dùng bắt đầu nhận hàng
    - allow_cod_on_failed : (boolean, default false) -- cho phép thu thêm khi giao thất bại
    - default_pickup_shift : (tiny integer, nullable) -- ca lấy hàng mong muốn
    - default_pickup_time : (timestamp, nullable) -- thời gian lấy hàng mong muốn
    - softDeletes
    - timestamps

# bảng lead_distribution_configs

    # note
    - bảng lưu trữ cấu hình phân bổ dữ liệu cho nhân viên
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id : (int, foreign key  -> organizations.id, not null) -- tổ chức có cấu hình
    - product_id : (int, foreign key -> products.id, nullable) -- sản phẩm áp dụng (để trống = tất cả)
    - name : (varchar(255), not null) -- tên cấu hình
    - created_by : (int, foreign key -> users.id, nullable) -- người tạo
    - updated_by : (int, foreign key -> users.id, nullable) -- người cập nhật gần nhất
    - softDeletes
    - timestamps
    - index [organization_id]

# bảng customers

    # note
    - xem là bảng data đầu vào ~ số được chia là số của khách hàng
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id : (int, foreign key  -> organizations.id, not null) -- tổ chức sở hữu khách hàng
    - username : (varchar(50), not null) -- tên khách hàng
    - phone : (varchar(20), nullable) -- số khách hàng ~ số được phân bổ (chia) cho nhân viên trong các nhóm
    - email : (varchar(255), nullable) -- email của khách hàng
    - address : (varchar(255), nullable) -- địa chỉ khách hàng
    - birthday : (date, nullable) -- ngày sinh khách hàng
    - customer_type : (tiny integer, not null) -- phân loại khách hàng đầu vào ~ số mới, số mới trùng, số cũ
    - interaction_status : (tiny integer, default 1, not null) -- trạng thái tương tác (1=FIRST_CALL, 2=SECOND_CALL, etc.)
    - assigned_staff_id : (int, foreign key -> users.id, nullable) -- nhân viên sở hữu số này ~ nhân viên nhận số đầu vào này
    - next_action_at : (timestamp, nullable) -- hẹn lịch gọi lại
    - product_id : (int, foreign key -> products.id, nullable) -- sản phẩm quan tâm
    - province_id : (unsigned int, nullable) -- tỉnh/thành phố
    - district_id : (unsigned int, nullable) -- quận/huyện
    - ward_id : (unsigned int, nullable) -- phường/xã
    - shipping_address : (varchar(255), nullable) -- địa chỉ giao hàng chi tiết
    - avatar : (varchar(255), nullable) -- ảnh đại diện
    - source : (varchar(100), nullable) -- tên nguồn (Facebook Ads, Landing Page, Website, Manual, etc.)
    - source_detail : (varchar(255), nullable) -- tên nguồn chi tiết VD: campaign, form v.v
    - source_id : (varchar(255), nullable) -- id từ source bên ngoài
    - note : (text, nullable) -- ghi chú của khách hàng
    - note_temp : (text, nullable) -- ghi chú tạm thời
    - softDeletes
    - timestamps
    - index[phone]
    - index[assigned_staff_id, customer_type]
    - index[source]
    - index[interaction_status]
    - index[next_action_at]
    - index[organization_id, customer_type]

# bảng lead_distribution_rules

    # note
    - bảng lưu chính cấu hình của phân bổ data
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - config_id : (int, foreign key -> lead_distribution_configs.id, not null) -- cấu hình cha sở hữu chi tiết cấu hình con
    - customer_type : (tiny integer, not null) -- loại data ~ loại khách hàng được định nghĩa đầu vào
    - staff_type : (tiny integer, not null) -- loại team được chia
    - distribution_method : (tiny integer, not null) -- loại phương thức được sử dụng để phân bổ
    - unique['distribution_method', 'customer_type', 'staff_type']
    - softDeletes
    - timestamps

# bảng lead_distribution_staff

    # note
    - bảng lưu nhân viên được gán trong cấu hình, danh sách nhân viên nhận được data theo cấu hình
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - config_id : (int, foreign key -> lead_distribution_configs.id, not null) -- cấu hình sở hữu
    - staff_id : (int, foreign key -> users.id, not null) -- nhân viên
    - weight : (integer, default 1) -- trọng số phân phối
    - softDeletes
    - timestamps
    - unique[config_id, staff_id]

# bảng integrations

    # note
    - Đây là bảng gốc mô tả một kết nối marketing của từng tổ chức (Facebook Ads, Landing Page, Website…), Toàn bộ cấu hình, webhook, field mapping… được gom vào JSON config
    # cấu trúc
    - id : (int, primary key, auto-increment)
    - organization_id : (unsignedBigInteger, foreign key → organizations.id, cascadeOnDelete)
    - name : (varchar(255), not null) — tên cấu hình hiển thị
    - status : (unsignedTinyInteger, default 0) — trạng thái:  0 = pending, 1 = connected, 2 = expired, 3 = error
    - status_message : (text, nullable) — mô tả chi tiết trạng thái
    - last_sync_at : (timestamp, nullable) — thời điểm đồng bộ gần nhất
    - config : (json, nullable) — chứa app_id, app_secret, webhook verify token, API keys, domain, pixel…
    - field_mapping : (json, nullable) — mapping field từ nguồn → bảng customers
    - created_by : (unsignedBigInteger, nullable, foreign key → users.id, nullOnDelete)
    - updated_by : (unsignedBigInteger, nullable, foreign key → users.id, nullOnDelete)
    - softDeletes
    - timestamps
    - index [organization_id]

# bảng integration_entities

    # note
    - Lưu từng "thực thể" thuộc một integration, Ví dụ đối với Facebook Ads: Page, Business Account (BMA), Ad Account, Pixel
    # cấu trúc
    - id : (int, primary key, auto-increment)
    - integration_id: (unsignedBigInteger, foreign key → integrations.id, cascadeOnDelete)
    - type: (unsignedSmallInteger) — enum IntegrationEntityType (VD: PAGE_META = 1)
    - external_id: (varchar(100)) — ID bên ngoài (page_id, ad_account_id…)
    - name: (varchar(255), nullable)
    - metadata: (json, nullable) — chứa quyền (permissions), email, timezone, picture…
    - status: (unsignedTinyInteger, default 1) — active / inactive
    - connected_at: (timestamp, nullable)
    - softDeletes
    - timestamps
    - index [integration_id, type]
    - index [external_id]

# bảng integration_tokens

    # note
    - Lưu tất cả token của Integration, Hỗ trợ nhiều loại token cùng lúc: user_token, long_lived_user_token, page_token, business_token, webhook_secret, Token có thể liên quan tới một entity cụ thể (page_token → page)
    # cấu trúc
    - id : (int, primary key, auto-increment)
    - integration_id: (unsignedBigInteger, foreign key → integrations.id, cascadeOnDelete)
    - entity_id: (unsignedBigInteger, nullable, foreign key → integration_entities.id, nullOnDelete)
    - type: (varchar(50)) — loại token
    - token: (text, encrypted)
    - scopes: (json, nullable) — danh sách quyền đã cấp
    - expires_at: (timestamp, nullable)
    - status: (unsignedTinyInteger, default 1) — active / expired
    - timestamps
    - index [integration_id, type]

# bảng orders

    # note
    - Quản lý đơn hàng từ telesale
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id : (int, foreign key -> organizations.id, not null) -- tổ chức sở hữu đơn hàng
    - customer_id : (int, foreign key -> customers.id, not null) -- khách hàng đặt hàng
    - code : (varchar(50), unique, not null) -- mã đơn hàng
    - status : (tiny integer, nullable) -- trạng thái (pending, confirmed, shipping, completed, cancelled)
    - total_amount : (decimal(15,2), default 0) -- tổng tiền
    - discount : (decimal(15,2), default 0) -- chiết khấu
    - shipping_fee : (decimal(15,2), default 0) -- phí vận chuyển
    - deposit : (decimal(15,2), default 0) -- tiền đặt cọc
    - cod_fee : (decimal(15,2), default 0) -- phí dịch vụ COD
    - cod_support_amount : (decimal(15,2), default 0) -- tiền hỗ trợ COD, trừ vào số tiền thực thu
    - collect_amount : (decimal(15,2), default 0) -- số tiền thực thu sau đặt cọc/hỗ trợ COD
    - ck1 : (decimal(5,2), default 0) -- chiết khấu 1 (%)
    - ck2 : (decimal(5,2), default 0) -- chiết khấu 2 (%)
    - shipping_method : (varchar(50), nullable) -- đơn vị vận chuyển (ghn, ghtk)
    - shipping_address : (varchar(255), nullable) -- địa chỉ giao hàng
    - province_id : (unsigned int, nullable) -- tỉnh/thành phố
    - district_id : (unsigned int, nullable) -- quận/huyện
    - ward_id : (unsigned int, nullable) -- phường/xã
    - note : (text, nullable) -- ghi chú
    - required_note : (varchar(50), nullable) -- lưu ý xem hàng GHN
    - created_by : (int, foreign key -> users.id, nullable) -- người tạo
    - updated_by : (int, foreign key -> users.id, nullable) -- người cập nhật
    - warehouse_id: (int, foreign key -> warehouses.id, nullable) -- kho hàng vận hành đơn
    - ghn_order_code: (varchar(100), nullable) -- mã đơn hàng từ GHN
    - ghn_expected_delivery_time: (timestamp, nullable) -- thời gian giao hàng dự kiến
    - ghn_service_type_id: (integer, nullable) -- loại dịch vụ GHN
    - ghn_service_name: (varchar(100), nullable) -- tên dịch vụ GHN
    - ghn_payment_type_id: (integer, nullable) -- hình thức thanh toán GHN
    - ghn_total_fee: (decimal(15,2), nullable) -- tổng phí ship GHN
    - ghn_response: (text, nullable) -- response từ GHN API
    - ghn_status: (varchar(50), nullable) -- trạng thái đơn hàng trên GHN
    - shipping_exception_reason_code: (varchar(50), nullable) -- mã lý do ngoại lệ giao vận (không nghe máy/sai địa chỉ/...)
    - shipping_exception_note: (text, nullable) -- ghi chú ngoại lệ giao vận
    - redelivery_attempt: (tiny integer, default 0) -- số lần yêu cầu giao lại
    - redelivery_schedule_at: (timestamp, nullable) -- lịch hẹn giao lại lần tiếp theo
    - ghn_posted_at: (timestamp, nullable) -- thời gian đăng đơn lên GHN
    - ghn_cancelled_at: (timestamp, nullable) -- thời gian hủy đơn trên GHN
    - weight: (integer, nullable) -- khối lượng (gram)
    - length: (integer, nullable) -- chiều dài (cm)
    - width: (integer, nullable) -- chiều rộng (cm)
    - height: (integer, nullable) -- chiều cao (cm)
    - insurance_value: (varchar(50), nullable) -- giá trị bảo hiểm
    - coupon: (varchar(50), nullable) -- mã giảm giá
    - shipping_provider_code: (varchar(100), nullable) -- mã nhà cung cấp vận chuyển
    - amount_recived_from_customer: (decimal(15,2), nullable) -- tiền nhận từ khách hàng
    - amout_support_fee: (decimal(15,2), default 0) -- phí hỗ trợ
    - ghn_cod_failed_amount: (decimal(15,2), nullable) -- phí giao thất bại thu tiền
    - ghn_content: (text, nullable) -- nội dung đơn hàng (GHN)
    - ghn_pick_station_id: (integer, nullable) -- trạm lấy hàng
    - ghn_deliver_station_id: (integer, nullable) -- trạm giao hàng
    - ghn_province_id: (integer, nullable) -- ID tỉnh/thành phố (GHN)
    - ghn_district_id: (integer, nullable) -- ID quận/huyện (GHN)
    - ghn_ward_code: (varchar(20), nullable) -- mã phường/xã (GHN)
    - invoice_status: (tinyint, default 1) -- trạng thái hóa đơn (1: Chưa xuất, 2: Đã xuất, 3: Đã hủy)
    - invoice_code: (varchar(100), nullable) -- mã số hóa đơn
    - invoice_url: (varchar(255), nullable) -- đường dẫn đến file PDF hóa đơn
    - invoice_at: (timestamp, nullable) -- thời điểm phát hành hóa đơn
    - debt_provision_amount: (decimal(15,2), default 0) -- số tiền dự phòng nợ khó đòi
    - is_written_off: (boolean, default false) -- trạng thái xóa nợ
    - write_off_at: (timestamp, nullable) -- thời điểm xóa nợ
    - write_off_by: (int, foreign key -> users.id, nullable) -- người thực hiện xóa nợ
    - softDeletes
    - timestamps
    - index [status]
    - index [organization_id, status]
    - index [created_at]

# bảng order_items

    # note
    - Chi tiết sản phẩm trong đơn hàng
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - order_id : (int, foreign key -> orders.id, not null) -- đơn hàng
    - product_id : (int, foreign key -> products.id, not null) -- sản phẩm
    - quantity : (integer, default 1) -- số lượng
    - price : (decimal(15,2), default 0) -- đơn giá
    - total : (decimal(15,2), default 0) -- thành tiền
    - timestamps

# bảng customer_interactions

    # note
    - Lịch sử tương tác với khách hàng (cuộc gọi, tin nhắn, email, ghi chú)
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - customer_id : (int, foreign key -> customers.id, not null) -- khách hàng
    - user_id : (int, foreign key -> users.id, nullable) -- nhân viên thực hiện
    - type : (varchar(50), not null) -- loại tương tác (call, sms, email, note, meeting)
    - direction : (varchar(20), nullable) -- chiều (inbound, outbound)
    - status : (tiny integer, nullable) -- trạng thái (completed, missed, failed)
    - duration : (integer, nullable) -- thời lượng cuộc gọi (giây)
    - content : (text, nullable) -- nội dung tin nhắn/ghi chú
    - metadata : (json, nullable) -- dữ liệu bổ sung (recording_url, attachments, etc.)
    - interacted_at : (timestamp, default CURRENT_TIMESTAMP) -- thời điểm tương tác
    - timestamps
    - index [customer_id, type]
    - index [interacted_at]

# bảng order_status_logs

    # note
    - Tracking thay đổi trạng thái đơn hàng
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - order_id : (int, foreign key -> orders.id, not null) -- đơn hàng
    - user_id : (int, foreign key -> users.id, nullable) -- người thay đổi
    - from_status : (varchar(50), nullable) -- trạng thái cũ
    - to_status : (varchar(50), not null) -- trạng thái mới
    - note : (text, nullable) -- ghi chú
    - timestamps
    - index [order_id, created_at]

# bảng warehouses

    # note
    - Quản lý kho
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id : (int, foreign key -> organizations.id, not null) -- tổ chức sở hữu kho
    - name : (varchar(255), not null) -- tên kho
    - code : (varchar(50), unique, not null) -- mã kho
    - address : (varchar(255), nullable) -- địa chỉ
    - province_id : (unsigned int, nullable) -- tỉnh/thành phố
    - district_id : (unsigned int, nullable) -- quận/huyện
    - ward_id : (unsigned int, nullable) -- phường/xã
    - note : (text, nullable) -- ghi chú
    - created_by : (int, foreign key -> users.id, nullable) -- người tạo
    - updated_by : (int, foreign key -> users.id, nullable) -- người cập nhật
    - manager_id : (int, foreign key -> users.id, nullable) -- người quản lý
    - manager_phone : (varchar(20), nullable) -- số điện thoại người quản lý
    - sender_name : (varchar(255), nullable) -- tên người gửi
    - sender_info : (text, nullable) -- thông tin người gửi
    - is_active : (tiny integer, default 1) -- trạng thái (active/inactive)
    - softDeletes
    - timestamps
    - index [organization_id]
    - index [code]
    - index [created_at]

# bảng warehouse_delivery_provinces

    # note
    - Quản lý tỉnh/thành phố có thể giao hàng
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - warehouse_id : (int, foreign key -> warehouses.id, not null) -- kho
    - province_id : (unsigned int, not null) -- tỉnh/thành phố
    - timestamps
    - index [warehouse_id, province_id]
    - index [province_id]

# bảng inventory_tickets

    # note
    - Quản lý phiếu nhập/xuất kho
    - Hỗ trợ 4 loại phiếu: Nhập kho (1), Xuất kho (2), Chuyển kho (3), Xuất hủy (4)
    - 3 trạng thái: Phiếu tạm (1), Hoàn thành (2), Đã hủy (3)
    - Chỉ phiếu tạm mới có thể chỉnh sửa/xóa
    - Phiếu hoàn thành không thể sửa, chỉ có thể hủy

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id : (int, foreign key -> organizations.id, not null) -- tổ chức sở hữu phiếu
    - code : (varchar(255), unique, not null) -- mã phiếu (auto-generate: INV-XXXXXXXX)
    - type : (tiny integer, not null) -- loại phiếu
        * 1 = Nhập kho (IMPORT)
        * 2 = Xuất kho (EXPORT)
        * 3 = Chuyển kho (TRANSFER)
        * 4 = Xuất hủy (CANCEL_EXPORT)
    - status : (tiny integer, default 1, not null) -- trạng thái phiếu
        * 1 = Phiếu tạm (DRAFT) - có thể chỉnh sửa
        * 2 = Hoàn thành (COMPLETED) - đã duyệt, không thể sửa
        * 3 = Đã hủy (CANCELLED) - đã hủy
    - warehouse_id : (int, foreign key -> warehouses.id, not null) -- kho thực hiện (cho Nhập/Xuất/Xuất hủy)
    - source_warehouse_id : (int, foreign key -> warehouses.id, nullable) -- kho nguồn (chỉ cho Chuyển kho)
    - target_warehouse_id : (int, foreign key -> warehouses.id, nullable) -- kho đích (chỉ cho Chuyển kho)
    - order_id : (int, foreign key -> orders.id, nullable) -- đơn hàng liên quan (dùng cho nhập hoàn)
    - is_sales_return : (boolean, default false) -- đánh dấu phiếu nhập hoàn để đồng bộ doanh thu thuần
    - note : (text, nullable) -- ghi chú
    - created_by : (int, foreign key -> users.id, nullable) -- người tạo
    - updated_by : (int, foreign key -> users.id, nullable) -- người cập nhật
    - approved_by : (int, foreign key -> users.id, nullable) -- người duyệt
    - approved_at : (timestamp, nullable) -- thời gian duyệt
    - softDeletes
    - timestamps
    - index [organization_id]
    - index [code]
    - index [type]
    - index [status]
    - index [created_at]

# bảng inventory_ticket_details

    # note
    - Chi tiết sản phẩm trong phiếu kho
    - Lưu số lượng và tồn kho tại thời điểm tạo phiếu

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - inventory_ticket_id : (int, foreign key -> inventory_tickets.id, not null) -- phiếu kho
    - product_id : (int, foreign key -> products.id, not null) -- sản phẩm
    - quantity : (integer, not null) -- số lượng nhập/xuất
    - unit_price : (decimal(15,2), default 0) -- đơn giá tại thời điểm nhập/xuất
    - batch_no : (varchar(100), nullable) -- mã lô hàng
    - expired_at : (date, nullable) -- hạn sử dụng của lô
    - bin_location_id : (unsignedBigInteger, nullable) -- vị trí lưu kho (warehouse_bins.id)
    - current_quantity : (integer, nullable) -- số lượng tồn kho tại thời điểm tạo phiếu (để tracking)
    - timestamps
    - index [inventory_ticket_id]
    - index [product_id]

# bảng inventory_ticket_logs

    # note
    - Lịch sử thay đổi phiếu kho
    - Tracking các thao tác: tạo, duyệt, hủy, sửa

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - inventory_ticket_id : (int, foreign key -> inventory_tickets.id, not null) -- phiếu kho
    - product_id : (int, foreign key -> products.id, not null) -- sản phẩm liên quan
    - note : (varchar(255), nullable) -- ghi chú thay đổi
    - reason : (varchar(255), nullable) -- lý do thay đổi
    - action : (varchar(50), nullable) -- loại thao tác (approve/cancel/ticket_in/ticket_out/...)
    - old_status : (tiny integer, nullable) -- trạng thái trước khi thao tác
    - new_status : (tiny integer, nullable) -- trạng thái sau thao tác
    - metadata_json : (json, nullable) -- dữ liệu bổ sung của thao tác (số lượng, loại phiếu, ...)
    - user_id : (int, foreign key -> users.id, nullable) -- người thực hiện
    - created_by : (int, foreign key -> users.id, nullable) -- người tạo log
    - updated_by : (int, foreign key -> users.id, nullable) -- người cập nhật log
    - softDeletes
    - timestamps
    - index [inventory_ticket_id]
    - index [product_id]
    - index [created_at]

# bảng product_warehouse

    # note
    - Bảng pivot quản lý tồn kho theo từng kho
    - Tracking số lượng tồn kho và số lượng chờ xuất
    - Mỗi sản phẩm có thể có tồn kho ở nhiều kho khác nhau

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - product_id : (int, foreign key -> products.id, not null) -- sản phẩm
    - warehouse_id : (int, foreign key -> warehouses.id, not null) -- kho
    - quantity : (integer, default 0, not null) -- số lượng tồn kho thực tế
    - pending_quantity : (integer, default 0, not null) -- số lượng chờ xuất (đã chốt đơn nhưng chưa xuất)
    - timestamps
    - unique [product_id, warehouse_id]
    - index [product_id]
    - index [warehouse_id]

# bảng inventory_movements

    # note
    - Ledger biến động kho theo từng thao tác nghiệp vụ.
    - Là nguồn dữ liệu chuẩn để tính nhập/xuất/tồn, pending, và truy vết chống thất thoát.
    - Mỗi dòng gắn ref nghiệp vụ (`inventory_ticket`, `order`, ...) để audit end-to-end.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, foreign key -> organizations.id, not null) -- tổ chức sở hữu biến động
    - warehouse_id: (int, foreign key -> warehouses.id, nullable) -- kho phát sinh biến động
    - product_id: (int, foreign key -> products.id, nullable) -- sản phẩm phát sinh biến động
    - ref_type: (varchar(50), nullable) -- loại chứng từ nguồn (inventory_ticket/order/...)
    - ref_id: (unsignedBigInteger, nullable) -- id chứng từ nguồn
    - movement_type: (varchar(20), not null) -- in/out/reserve/release/consume/transfer_in/transfer_out/return_in (InventoryMovementType)
    - quantity_before: (integer, default 0) -- tồn thực tế trước biến động
    - quantity_change: (integer, default 0) -- phần thay đổi tồn thực tế (+/-)
    - quantity_after: (integer, default 0) -- tồn thực tế sau biến động
    - pending_before: (integer, default 0) -- pending trước biến động
    - pending_change: (integer, default 0) -- phần thay đổi pending (+/-)
    - pending_after: (integer, default 0) -- pending sau biến động
    - reason_code: (varchar(50), nullable) -- mã lý do nghiệp vụ
    - reason_note: (varchar(255), nullable) -- ghi chú lý do nghiệp vụ
    - actor_id: (int, foreign key -> users.id, nullable) -- người thực hiện thao tác
    - occurred_at: (timestamp, nullable) -- thời điểm nghiệp vụ xảy ra
    - timestamps
    - index [organization_id, warehouse_id, product_id]
    - index [ref_type, ref_id]
    - index [movement_type, occurred_at]

# bảng warehouse_bins

    # note
    - Quản lý vị trí vật lý trong kho (kệ/tầng/bin).
    - Dùng để map tồn kho theo vị trí và áp rule chống mix SKU khi cần.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - warehouse_id: (int, foreign key -> warehouses.id, not null) -- kho sở hữu vị trí
    - code: (varchar(50), not null) -- mã vị trí (A1/B2/...)
    - name: (varchar(120), not null) -- tên vị trí hiển thị
    - allow_mix_sku: (boolean, default true) -- cho phép chứa nhiều SKU trong cùng vị trí
    - is_active: (boolean, default true) -- trạng thái sử dụng vị trí
    - timestamps
    - unique [warehouse_id, code]

# bảng shipping_config_for_warehouses

    # note
    - Cấu hình giao hàng GHN riêng cho từng kho
    - Mỗi kho có thể có cấu hình GHN riêng
    - Hỗ trợ nhiều tài khoản GHN cho các kho khác nhau

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - warehouse_id : (int, foreign key -> warehouses.id, not null) -- kho áp dụng cấu hình
    - organization_id : (int, foreign key -> organizations.id, not null) -- tổ chức sở hữu
    - account_name : (varchar(255), not null) -- tên tài khoản GHN
    - api_token : (varchar(255), not null) -- API Token GHN
    - store_id : (varchar(255), nullable) -- ID cửa hàng GHN
    - use_insurance : (boolean, default false) -- sử dụng bảo hiểm
    - insurance_limit : (unsigned big integer, nullable) -- giá trị bảo hiểm tối đa
    - required_note : (varchar(255), nullable) -- lựa chọn xem hàng (CHOTHUHANG, CHOXEMHANGKHONGTHU, KHONGCHOXEMHANG)
    - pickup_shift : (varchar(255), nullable) -- ca lấy hàng (1: sáng, 2: chiều, 3: tối)
    - cod_failed_amount : (decimal(15,0), default 0) -- số tiền thu khi giao hàng thất bại
    - fix_receiver_phone : (boolean, default false) -- cố định số điện thoại người nhận
    - is_default : (boolean, default false) -- cấu hình mặc định cho kho này
    - timestamps
    - index [warehouse_id]
    - index [organization_id]

# Quan hệ giữa các bảng Inventory

    ## Workflow quản lý kho:
    1. Tạo phiếu (inventory_tickets) với trạng thái DRAFT
    2. Thêm sản phẩm vào phiếu (inventory_ticket_details)
    3. Duyệt phiếu -> chuyển sang COMPLETED
    4. Cập nhật tồn kho (product_warehouse)
    5. Ghi log thay đổi (inventory_ticket_logs)

    ## Loại phiếu và logic cập nhật tồn kho:

    ### Nhập kho (IMPORT):
    - warehouse_id: kho nhập
    - Khi duyệt: quantity += số lượng nhập

    ### Xuất kho (EXPORT):
    - warehouse_id: kho xuất
    - Khi duyệt: quantity -= số lượng xuất
    - Validate: quantity >= số lượng xuất

    ### Chuyển kho (TRANSFER):
    - source_warehouse_id: kho nguồn
    - target_warehouse_id: kho đích
    - Khi duyệt:
        * Kho nguồn: quantity -= số lượng
        * Kho đích: quantity += số lượng
    - Validate: kho nguồn quantity >= số lượng chuyển

    ### Xuất hủy (CANCEL_EXPORT):
    - warehouse_id: kho xuất hủy
    - Khi duyệt: quantity -= số lượng hủy
    - Validate: quantity >= số lượng hủy

    ## Pending Quantity (Số lượng chờ xuất):
    - Tăng khi: Chốt đơn hàng (order status = CONFIRMED)
    - Giảm khi:
        * Xuất kho thực tế (inventory ticket EXPORT được duyệt)
        * Hủy đơn hàng (order status = CANCELLED)
    - Công thức tồn khả dụng: quantity - pending_quantity

# bảng exchange_rates

    # note
    - Bảng lưu trữ tỉ giá quy đổi theo ngày (cho đơn vị nước ngoài)
    - Mỗi tổ chức có thể có nhiều tỉ giá cho các loại tiền tệ khác nhau
    - Tỉ giá có thể nhập tay hoặc tự động từ API

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, foreign key -> organizations.id, not null) -- tổ chức sở hữu tỉ giá
    - rate_date: (date, not null) -- ngày áp dụng tỉ giá
    - from_currency: (varchar(3), default 'VND') -- đơn vị tiền tệ gốc (VND)
    - to_currency: (varchar(3), not null) -- đơn vị tiền tệ đích (USD, EUR, ...)
    - rate: (decimal(15, 6), not null) -- tỉ giá quy đổi
    - source: (varchar(50), default 'manual') -- nguồn: manual (nhập tay), api (tự động từ API)
    - note: (text, nullable) -- ghi chú
    - created_by: (int, foreign key -> users.id, nullable) -- người tạo
    - timestamps
    - softDeletes
    - unique [organization_id, rate_date, to_currency]
    - index [organization_id, rate_date]

# bảng reconciliations

    # note
    - Bảng đối soát với GHN (Giao Hàng Nhanh)
    - Lưu trữ thông tin đối soát theo ngày: tiền COD, phí giao hàng, phí kho
    - Hỗ trợ quy đổi tỉ giá cho đơn vị nước ngoài

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, foreign key -> organizations.id, not null) -- tổ chức
    - reconciliation_date: (date, not null) -- ngày đối soát
    - order_id: (int, foreign key -> orders.id, nullable) -- đơn hàng liên quan (nếu đối soát theo đơn)
    - ghn_order_code: (varchar(100), nullable) -- mã đơn GHN
    - ghn_to_name: (varchar(255), nullable) -- tên người nhận từ GHN
    - ghn_to_phone: (varchar(20), nullable) -- số điện thoại người nhận từ GHN
    - ghn_to_address: (text, nullable) -- địa chỉ người nhận từ GHN
    - ghn_status_label: (varchar(255), nullable) -- tên trạng thái từ GHN
    - ghn_created_at: (timestamp, nullable) -- ngày tạo đơn trên GHN
    - ghn_updated_at: (timestamp, nullable) -- ngày cập nhật mới nhất từ GHN
    - ghn_items: (json, nullable) -- danh sách sản phẩm từ GHN
    - ghn_payment_type_id: (int, nullable) -- loại thanh toán GHN
    - ghn_weight: (int, nullable) -- khối lượng theo GHN
    - ghn_content: (text, nullable) -- nội dung đơn hàng theo GHN
    - ghn_required_note: (varchar(255), nullable) -- yêu cầu khi giao hàng theo GHN
    - ghn_employee_note: (text, nullable) -- ghi chú của nhân viên GHN
    - ghn_cod_failed_amount: (decimal(15, 2), default 0) -- phí GTB (Giao thất bại thu tiền)
    - cod_amount: (decimal(15, 2), default 0) -- tiền COD
    - shipping_fee: (decimal(15, 2), default 0) -- phí giao hàng
    - storage_fee: (decimal(15, 2), default 0) -- phí kho
    - total_fee: (decimal(15, 2), default 0) -- tổng phí
    - exchange_rate_id: (int, foreign key -> exchange_rates.id, nullable) -- tỉ giá (cho đơn vị nước ngoài)
    - converted_amount: (decimal(15, 2), nullable) -- số tiền sau khi quy đổi theo tỉ giá
    - status: (tinyint, default 1) -- 1: pending, 2: confirmed, 3: cancelled, 4: paid
    - note: (text, nullable) -- ghi chú
    - created_by: (int, foreign key -> users.id, nullable) -- người tạo
    - confirmed_by: (int, foreign key -> users.id, nullable) -- người xác nhận
    - confirmed_at: (timestamp, nullable) -- thời gian xác nhận
    - timestamps
    - softDeletes
    - index [organization_id, reconciliation_date]
    - index [order_id]
    - index [ghn_order_code]

# bảng expenses

    # note
    - Bảng lưu trữ các chi phí phát sinh
    - Phân loại: lương, MKT, đối soát giao hàng, quản lý doanh nghiệp, văn phòng, chi tiêu khác, giá vốn
    - Có thể liên kết với đơn hàng (nếu là chi phí giao hàng tự động) hoặc đối soát

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, foreign key -> organizations.id, not null) -- tổ chức
    - expense_date: (date, not null) -- ngày phát sinh chi phí
    - category: (tinyint, not null) -- loại chi phí: 1: salary, 2: marketing, 3: shipping, 4: management, 5: office, 6: other, 7: cost_of_goods
    - description: (varchar(500), not null) -- mô tả chi phí
    - amount: (decimal(15, 2), not null) -- số tiền
    - order_id: (int, foreign key -> orders.id, nullable) -- đơn hàng liên quan (nếu là chi phí giao hàng tự động)
    - reconciliation_id: (int, foreign key -> reconciliations.id, nullable) -- đối soát liên quan
    - note: (text, nullable) -- ghi chú
    - created_by: (int, foreign key -> users.id, nullable) -- người tạo
    - timestamps
    - softDeletes
    - index [organization_id, expense_date]
    - index [category]
    - index [order_id]

# bảng revenues

    # note
    - Bảng lưu trữ doanh thu khác (nhập tay)
    - Doanh thu từ đơn hàng được tính tự động từ bảng orders

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, foreign key -> organizations.id, not null) -- tổ chức
    - revenue_date: (date, not null) -- ngày phát sinh doanh thu
    - description: (varchar(500), not null) -- mô tả doanh thu
    - amount: (decimal(15, 2), not null) -- số tiền
    - note: (text, nullable) -- ghi chú
    - created_by: (int, foreign key -> users.id, nullable) -- người tạo
    - timestamps
    - softDeletes
    - index [organization_id, revenue_date]

# bảng user_assigned_staff

    # note
    - Bảng pivot gán Sale/CSKH phụ trách cho từng khách hàng.
    - Dùng cho phân quyền dữ liệu tác nghiệp và lịch sử phân công.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - staff_id: (int, foreign key -> users.id, not null) -- nhân sự được gán
    - customer_id: (int, foreign key -> customers.id, not null) -- khách hàng được phụ trách
    - timestamps

# bảng customer_status_logs

    # note
    - Lưu nhật ký chuyển trạng thái chăm sóc khách hàng theo từng lần tác nghiệp.
    - Là nguồn dữ liệu chính cho báo cáo phễu chốt theo bước gọi/chăm sóc.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - customer_id: (int, foreign key -> customers.id, not null)
    - user_id: (int, foreign key -> users.id, nullable) -- người thực hiện thao tác
    - from_status: (tinyint, nullable) -- trạng thái trước khi chuyển
    - to_status: (tinyint, not null) -- trạng thái sau khi chuyển
    - note: (text, nullable) -- ghi chú tác nghiệp
    - reason: (tinyint, nullable) -- lý do chuyển trạng thái
    - timestamps
    - index [customer_id, created_at]

# bảng black_list

    # note
    - Danh sách đen khách hàng cần chặn/tạm ngưng chăm sóc.
    - Lưu lý do blacklist để kiểm soát chất lượng data và vận hành CSKH.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - customer_id: (int, foreign key -> customers.id, not null)
    - user_id: (int, foreign key -> users.id, nullable) -- người thêm blacklist
    - note: (text, nullable) -- ghi chú
    - reason: (tinyint, nullable) -- mã lý do blacklist
    - timestamps
    - index [customer_id, created_at]

# bảng call_record_of_telesale_operations

    # note
    - Lưu file ghi âm cuộc gọi trong màn hình tác nghiệp telesale.
    - Liên kết đến interaction/status log để truy vết nội dung tư vấn và QA cuộc gọi.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - customer_id: (int, foreign key -> customers.id, nullable)
    - staff_id: (int, foreign key -> users.id, nullable)
    - customer_interaction_id: (int, foreign key -> customer_interactions.id, nullable)
    - customer_status_log_id: (int, foreign key -> customer_status_logs.id, nullable)
    - path_record: (varchar(255), nullable) -- đường dẫn file ghi âm
    - timestamps
    - softDeletes

# bảng currencies

    # note
    - Danh mục mã tiền tệ dùng cho nghiệp vụ quy đổi, báo cáo tài chính và đối soát.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - code: (varchar(3), not null, unique) -- mã ISO tiền tệ (VD: VND, USD)
    - timestamps

# bảng funds

    # note
    - Quỹ tiền theo từng tổ chức; phản ánh số dư hiện tại để hạch toán thu/chi nội bộ.
    - Số dư đầu kỳ chỉ được phép chỉnh khi chưa phát sinh giao dịch.
    - Hỗ trợ khóa nghiệp vụ theo toàn quỹ/user/team qua rule riêng.
    - Model: `App\Models\Fund`.
    - Nghiệp vụ chính:
      - Quản trị quỹ đầu kỳ và trạng thái khóa toàn quỹ.
      - Là bản ghi cha của toàn bộ thu/chi tại `fund_transactions`.
      - Là điểm vào để áp rule khóa thao tác theo user/team.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, foreign key -> organizations.id, not null)
    - balance: (decimal(8,2), default 0) -- số dư quỹ hiện tại
    - currency: (varchar(3), default 'VND') -- tiền tệ quỹ
    - fund_type: (varchar(20), default 'cash') -- loại quỹ (cash/bank/other)
    - is_locked: (boolean, default false) -- khóa thao tác toàn quỹ
    - timestamps
    - softDeletes

# bảng fund_transactions

    # note
    - Lịch sử biến động quỹ (thu/chi/điều chỉnh) để đối chiếu tài chính.
    - Mỗi bản ghi lưu số dư sau giao dịch để truy xuất sổ quỹ theo thời điểm.
    - Model: `App\Models\FundTransaction`.
    - Nghiệp vụ chính:
      - Lưu phiếu Thu/Chi đầy đủ thông tin chứng từ (ngày, đối tượng nộp/nhận, mục đích, diễn giải).
      - Hỗ trợ đa tiền tệ qua `currency`, `exchange_rate`, `amount_base`.
      - Cho phép sửa/xóa phiếu quá khứ và service sẽ recalculation lại `balance_after`.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - fund_id: (int, foreign key -> funds.id, not null)
    - type: (tinyint, not null) -- loại giao dịch (thu/chi/điều chỉnh)
    - transaction_code: (varchar(255), nullable) -- mã nghiệp vụ
    - transaction_id: (varchar(255), nullable) -- id tham chiếu ngoài hệ thống
    - transaction_date: (date, nullable) -- ngày chứng từ
    - balance_after: (decimal(8,2), not null) -- số dư quỹ sau giao dịch
    - amount: (decimal(8,2), not null) -- giá trị giao dịch
    - counterparty_name: (varchar(255), nullable) -- người nộp/nhận
    - currency: (varchar(3), default 'VND') -- tiền tệ giao dịch
    - exchange_rate: (decimal(14,6), nullable) -- tỷ giá quy đổi
    - amount_base: (decimal(20,2), nullable) -- số tiền quy đổi bản tệ
    - description: (varchar(255), nullable) -- diễn giải giao dịch
    - purpose: (varchar(255), nullable) -- mục đích thu/chi
    - note: (text, nullable) -- ghi chú nghiệp vụ
    - status: (tinyint, not null) -- trạng thái giao dịch
    - updated_by: (int, nullable) -- người cập nhật gần nhất
    - timestamps
    - softDeletes

# bảng fund_lock_rules

    # note
    - Cấu hình khóa thao tác quỹ theo hành động (add/edit/delete) và phạm vi (toàn quỹ/user/team).
    - Dùng để enforce quyền thao tác ở cả UI và tầng service.
    - Model: `App\Models\FundLockRule`.
    - Nghiệp vụ chính:
      - Khóa chi tiết theo `action` và `scope_type`.
      - Hỗ trợ cấu hình hàng loạt cho nhiều user/team.
      - Là nguồn kiểm tra trước khi tạo/sửa/xóa giao dịch quỹ.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - fund_id: (int, foreign key -> funds.id, not null)
    - action: (varchar(20), not null) -- add|edit|delete
    - scope_type: (varchar(20), default 'global') -- global|user|team
    - user_id: (int, nullable) -- target user nếu scope=user
    - team_id: (int, nullable) -- target team nếu scope=team
    - is_locked: (boolean, default true)
    - created_by: (int, nullable)
    - updated_by: (int, nullable)
    - timestamps

# bảng fund_lock_audits

    # note
    - Nhật ký khóa/mở quỹ immutable phục vụ thanh tra nội bộ.
    - Model: `App\Models\FundLockAudit`.
    - Nghiệp vụ chính:
      - Ghi nhận ai thay đổi rule khóa, khóa gì, áp cho đối tượng nào, thời điểm nào.
      - Là dữ liệu read-only cho màn hình audit trail của quỹ.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - fund_id: (int, foreign key -> funds.id, not null)
    - action: (varchar(20), not null)
    - is_locked: (boolean, not null)
    - scope_type: (varchar(20), default 'global')
    - target_user_id: (int, nullable)
    - target_team_id: (int, nullable)
    - metadata_json: (json, nullable)
    - changed_by: (int, nullable)
    - changed_at: (timestamp, nullable)
    - timestamps

# bảng fund_transaction_attachments

    # note
    - Lưu lịch sử phiên bản chứng từ đính kèm của giao dịch quỹ, không ghi đè file cũ.
    - Model: `App\Models\FundTransactionAttachment`.
    - Nghiệp vụ chính:
      - Mỗi lần cập nhật file tạo version mới (`version` tăng dần).
      - Bảo toàn chứng từ gốc để chống thay thế/chỉnh sửa không kiểm soát.
      - Hỗ trợ truy vết người upload và thời điểm upload.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - fund_transaction_id: (int, foreign key -> fund_transactions.id, not null)
    - version: (unsigned int, default 1)
    - file_path: (varchar(255), not null)
    - original_name: (varchar(255), nullable)
    - mime_type: (varchar(255), nullable)
    - file_size: (unsigned bigint, nullable)
    - uploaded_by: (int, nullable)
    - uploaded_at: (timestamp, nullable)
    - timestamps

# bảng telesale_notification_aggregates

    # note
    - Bảng gom nhóm thông báo lead trùng theo tổ chức để tránh spam realtime.
    - Dùng cho badge thông báo và thống kê số lead trùng đã nhận.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, foreign key -> organizations.id, not null)
    - duplicate_hash: (varchar(191), not null, indexed) -- hash phone/email chuẩn hóa
    - lead_count: (unsigned int, default 1) -- số lead trong nhóm trùng
    - last_customer_id: (int, foreign key -> customers.id, nullable) -- lead gần nhất trong nhóm
    - last_notified_at: (timestamp, nullable) -- thời điểm đẩy thông báo gần nhất
    - timestamps
    - unique [organization_id, duplicate_hash]

# bảng sale_kpi_targets

    # note
    - Cấu hình KPI theo tháng cho từng Sale trong từng tổ chức.
    - Là nguồn tính tiến độ KPI, thưởng dự kiến và dashboard hiệu suất.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, foreign key -> organizations.id, not null)
    - user_id: (int, foreign key -> users.id, not null)
    - month: (varchar(7), not null, indexed) -- định dạng YYYY-MM
    - kpi_amount: (decimal(15,2), default 0) -- doanh số mục tiêu
    - base_salary: (decimal(15,2), default 0) -- lương cứng
    - bonus_rules_json: (json, nullable) -- rule thưởng theo mốc KPI
    - timestamps
    - unique [organization_id, user_id, month]

# bảng sale_levels

    # note
    - Cấu hình level Sale (mới/cứng/giỏi) theo mục tiêu KPI.
    - Chứa ngưỡng cảnh báo để tô màu KPI theo trạng thái tốt/trung bình/chưa tốt.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, foreign key -> organizations.id, not null)
    - name: (varchar(100), not null) -- tên level
    - kpi_target: (decimal(15,2), default 0) -- target chuẩn của level
    - warning_thresholds_json: (json, nullable) -- cấu hình ngưỡng màu KPI
    - is_active: (boolean, default true)
    - timestamps

# bảng pushsale_rule_sets

    # note
    - Bộ rule tính doanh số/KPI theo chuẩn Pushsale cho từng nhóm Sale.
    - Dùng để áp dụng lại công thức trên toàn bộ báo cáo khi chọn rule set.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, foreign key -> organizations.id, not null)
    - name: (varchar(100), not null)
    - rules_json: (json, nullable) -- cấu hình hệ số/điều kiện tính KPI
    - is_default: (boolean, default false)
    - timestamps

# bảng team_report_scopes

    # note
    - Mapping phạm vi dữ liệu báo cáo cho Leader theo team/group.
    - Hỗ trợ rule phân quyền: Admin full, Leader theo scope, Sale chỉ dữ liệu của mình.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, foreign key -> organizations.id, not null)
    - leader_id: (int, foreign key -> users.id, not null)
    - team_id: (int, foreign key -> teams.id, nullable)
    - group_key: (varchar(100), nullable) -- key nhóm mở rộng ngoài team_id
    - timestamps
    - index [organization_id, leader_id]

# bảng report_export_jobs

    # note
    - Theo dõi tiến trình export báo cáo theo bộ lọc hiện tại của người dùng.
    - Cho phép chạy nền khi dữ liệu lớn để tránh timeout UI.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - user_id: (int, foreign key -> users.id, not null)
    - report_type: (varchar(100), not null, indexed) -- loại báo cáo export
    - filters_json: (json, nullable) -- snapshot bộ lọc tại thời điểm export
    - row_count: (unsigned int, default 0) -- số dòng dữ liệu dự kiến
    - status: (varchar(30), default 'pending', indexed) -- pending/processing/completed/failed
    - file_path: (varchar(255), nullable) -- đường dẫn file kết quả
    - error_message: (text, nullable) -- chi tiết lỗi nếu thất bại
    - completed_at: (timestamp, nullable) -- thời điểm hoàn tất export
    - timestamps
# bảng accounting_periods

    # note
    - Bảng lưu trữ các kỳ kế toán

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, foreign key -> organizations.id, not null) -- tổ chức
    - month: (tinyint, not null) -- tháng
    - year: (smallint, not null) -- năm
    - closed_at: (timestamp, nullable) -- thời gian đóng kỳ
    - closed_by: (int, foreign key -> users.id, nullable) -- người đóng kỳ
    - note: (text, nullable) -- ghi chú
    - timestamps
    - unique [organization_id, month, year]

# bảng shipping_shops

    # note
    - Bảng lưu trữ thông tin các kho hàng của tổ chức

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - shop_id: (int, not null) -- ID của GHN
    - name: (varchar(255), not null) -- tên kho hàng
    - phone: (varchar(20), nullable) -- số điện thoại
    - address: (varchar(255), nullable) -- địa chỉ
    - province_id: (int, nullable) -- tỉnh/thành phố
    - district_id: (int, nullable) -- quận/huyện
    - ward_code: (varchar(20), nullable) -- phường/xã
    - organization_id: (int, foreign key -> organizations.id, not null) -- tổ chức
    - is_default: (boolean, default false) -- kho hàng mặc định
    - status: (boolean, default true) -- trạng thái
    - timestamps
    - unique [shop_id, organization_id]

# bảng facebook_event_logs

    # note
    - Nhật ký gửi Facebook Pixel/CAPI server-side cho các event AddToCart/Lead/Purchase.
    - Model: `App\Models\FacebookEventLog`.
    - Nghiệp vụ chính:
      - Lưu payload gốc + payload đã hash SHA256 trước khi gửi.
      - Theo dõi retry_count, next_retry_at, last_error để retry có backoff.
      - Truy vết theo integration và tổ chức khi cần debug.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, indexed)
    - integration_id: (int, nullable, indexed)
    - entity_id: (int, nullable, indexed)
    - event_name: (varchar(50), not null)
    - event_id: (varchar(120), nullable, indexed)
    - payload_json: (json, not null)
    - hashed_payload_json: (json, nullable)
    - status: (varchar(20), default 'pending', indexed) -- pending/retrying/processed/failed
    - retry_count: (unsigned int, default 0)
    - last_error: (text, nullable)
    - next_retry_at: (timestamp, nullable, indexed)
    - processed_at: (timestamp, nullable)
    - timestamps

# bảng website_lead_ingest_logs

    # note
    - Log ingest lead từ Website V2 endpoint strict JSON schema.
    - Model: `App\Models\WebsiteLeadIngestLog`.
    - Nghiệp vụ chính:
      - Ghi nhận request payload, normalized payload, và lỗi field-level nếu fail schema.
      - Dùng cho debug tích hợp web và ping test MKT_13.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, indexed)
    - integration_id: (int, nullable, indexed)
    - site_id: (varchar(100), indexed)
    - request_id: (varchar(120), nullable, indexed)
    - status: (varchar(20), default 'received', indexed) -- received/validated/processed/invalid
    - payload_json: (json, not null)
    - normalized_json: (json, nullable)
    - error_json: (json, nullable)
    - received_at: (timestamp, nullable, indexed)
    - timestamps

# bảng marketing_scoring_rule_sets

    # note
    - Bộ quy đổi điểm xếp hạng Marketing (MKT_17).
    - Model: `App\Models\MarketingScoringRuleSet`.
    - Nghiệp vụ chính:
      - Cấu hình trọng số đơn/contact/doanh số và bonus conversion.
      - Cho phép chọn rule set trên Phong thần bảng để tính rank công bằng.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, indexed)
    - name: (varchar(255), not null)
    - rules_json: (json, nullable)
    - is_default: (boolean, default false)
    - is_active: (boolean, default true)
    - timestamps

# bảng marketing_budgets

    # note
    - Ngân sách Marketing theo ngày/kênh/chiến dịch (MKT_07, MKT_14).
    - Model: `App\Models\MarketingBudget`.
    - Nghiệp vụ chính:
      - Là baseline để so sánh chi tiêu thực tế và trigger cảnh báo vượt ngân sách.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, indexed)
    - date: (date, indexed)
    - channel: (varchar(100), indexed)
    - campaign: (varchar(150), indexed)
    - budget_amount: (decimal(20,2), default 0)
    - timestamps
    - unique [organization_id, date, channel, campaign]

# bảng marketing_spends

    # note
    - Chi tiêu thực tế Marketing theo ngày/kênh/chiến dịch.
    - Model: `App\Models\MarketingSpend`.
    - Nghiệp vụ chính:
      - Lưu actual spend + fee để tính CPL, ROI, KPI vận hành (MKT_08/MKT_10).

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, indexed)
    - date: (date, indexed)
    - channel: (varchar(100), indexed)
    - campaign: (varchar(150), indexed)
    - actual_spend: (decimal(20,2), default 0)
    - fee_amount: (decimal(20,2), default 0)
    - note: (text, nullable)
    - timestamps

# bảng marketing_spend_attachments

    # note
    - Version file chứng từ chi phí marketing (MKT_15).
    - Model: `App\Models\MarketingSpendAttachment`.
    - Nghiệp vụ chính:
      - Mỗi lần upload tạo version mới, không ghi đè file cũ.
      - Audit được ai upload và thời điểm upload.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - marketing_spend_id: (int, indexed)
    - version: (unsigned int, default 1)
    - file_path: (varchar(255), not null)
    - uploaded_by: (int, nullable)
    - uploaded_at: (timestamp, nullable)
    - timestamps
    - unique [marketing_spend_id, version]

# bảng marketing_alert_logs

    # note
    - Nhật ký cảnh báo vượt ngân sách / ROI thấp / chi tiêu bất thường (MKT_14).
    - Model: `App\Models\MarketingAlertLog`.
    - Nghiệp vụ chính:
      - Lưu trace cảnh báo theo channel/campaign và payload phân tích tại thời điểm trigger.

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id: (int, indexed)
    - alert_type: (varchar(50), indexed)
    - severity: (varchar(30), indexed) -- warning/high
    - channel: (varchar(100), nullable, indexed)
    - campaign: (varchar(150), nullable, indexed)
    - payload_json: (json, nullable)
    - triggered_at: (timestamp, nullable, indexed)
    - resolved_at: (timestamp, nullable, indexed)
    - timestamps
