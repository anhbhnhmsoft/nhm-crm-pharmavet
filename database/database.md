-- Database Schema Documentation

# provinces 

    # note
    - bảng lưu trữ thông tin các tỉnh/thành phố

    # cấu trúc 
    - id: (int, primary key, auto-increment)
    - code_v1: (varchar(2), not null, unique)
    - code_v2: (varchar(2), nullable, unique)
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
    - name: (varchar(255), not null)
    - code_v1: (varchar(3), not null, unique)
    - code_v2: (varchar(3), nullable, unique)
    - code_name: (varchar(20), not null, unique)
    - division_type: (varchar(50), not null)
    - metadata: (json, nullable)
    - timestamps
    - province_id: (unsignedBigInteger, not null) -- tỉnh/thành phố sở hữu quận/huyện
    - province_code_v1: (varchar(2), nullable, unique)

# wards

    # note
    - bảng lưu trữ thông tin các phường/xã

    # cấu trúc
    - id: (int, primary key, auto-increment)
    - name: (varchar(255), not null)
    - code_v1: (varchar(5), not null, unique)
    - code_v2: (varchar(5), nullable, unique)
    - code_name: (varchar(20), not null, unique)
    - division_type: (varchar(50), not null)
    - metadata: (json, nullable)
    - timestamps
    - district_id: (unsignedBigInteger, not null) -- quận/huyện sở hữu phường/xã
    - district_code_v1: (varchar(3), nullable, unique)
    - province_code_v1: (varchar(2), nullable, unique)
    - province_code_v2: (varchar(2), nullable, unique)

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
    - assigned_staff_id : (int, foreign key -> users.id, nullable) -- nhân viên sở hữu số này ~ nhân viên nhận số đầu vào này
    - status : (varchar(50), default 'new') -- trạng thái tác nghiệp (new, processing, potential, unreachable, closed, cancelled)
    - next_action_at : (timestamp, nullable) -- hẹn lịch gọi lại
    - province_id : (unsigned int, nullable) -- tỉnh/thành phố
    - district_id : (unsigned int, nullable) -- quận/huyện
    - ward_id : (unsigned int, nullable) -- phường/xã
    - shipping_address : (varchar(255), nullable) -- địa chỉ giao hàng chi tiết
    - source : (varchar(100), nullable) -- tên nguồn (Facebook Ads, Landing Page, Website, Manual, etc.)
    - source_detail : (varchar(255), nullable) -- tên nguồn chi tiết VD: campaign, form v.v
    - source_id : (varchar(255), nullable) -- id từ source bên ngoài
    - note : (text, nullable) -- ghi chú của khách hàng
    - softDeletes
    - timestamps
    - index[phone]
    - index[assigned_staff_id, customer_type]
    - index[source]

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
    - status : (varchar(50), default 'pending') -- trạng thái (pending, confirmed, shipping, completed, cancelled)
    - total_amount : (decimal(15,2), default 0) -- tổng tiền
    - discount : (decimal(15,2), default 0) -- chiết khấu
    - shipping_fee : (decimal(15,2), default 0) -- phí vận chuyển
    - shipping_method : (varchar(50), nullable) -- đơn vị vận chuyển (ghn, ghtk)
    - shipping_address : (varchar(255), nullable) -- địa chỉ giao hàng
    - province_id : (unsigned int, nullable) -- tỉnh/thành phố
    - district_id : (unsigned int, nullable) -- quận/huyện
    - ward_id : (unsigned int, nullable) -- phường/xã
    - note : (text, nullable) -- ghi chú
    - created_by : (int, foreign key -> users.id, nullable) -- người tạo
    - updated_by : (int, foreign key -> users.id, nullable) -- người cập nhật
    - softDeletes
    - timestamps

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
