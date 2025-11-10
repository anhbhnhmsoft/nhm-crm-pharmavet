-- Database Schema Documentation

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
    - weight : (unsigned in, not null ) -- khối lượng sản phẩm
    - cost_price : (decimal(15, 2), nullable) -- Giá nhập/Giá vốn của sản phấm
    - sale_price : (decimal(15, 2), nullable) -- Giá bán niêm yết của sản phẩm.
    - image : (varchar(255), nullable) -- hình ảnh sản phẩm
    - description : (text, nullable) -- miêu tả sản phẩm
    - barcode : (varchar(100), nullable) -- mã vạch sản phẩm
    - type : (unsigned tiny integer, not null) -- Loại sản phẩm
    - length : (varchar(50), nullable) -- chiều dài
    - height : (varchar(50), nullable) -- chiều cao
    - width : (varchar(50), nullable) -- chiều rộng
    - quantity : (unsiged integer, not null) -- số lượng sản phẩm
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
    - product_id : (int, foreign key -> users.id, not null) -- người dùng thực hiện hành động
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

# bảng shifts

    # note
    - bảng lưu ca làm việc của người việc.
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - name : (varchar(255), not null) -- tên ca làm việc
    - organization_id : (int, foreign key -> organizations.id, not null) -- tổ chức của ca làm việc
    - name : (varchar(255), not null) -- tên ca làm việc
    - start_time : (timestamp, not null) -- giờ bắt đầu ca
    - end_time : (timestamp, not null) -- giờ kết thúc ca
    - softDeletes
    - timestamps

# bảng user_shift

    # note
    - bảng lưu người dùng trong ca làm việc
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - user_id : (int, foreign key -> users.id, not null) -- người dùng có trong ca làm việc
    - shift_id : (int, foreign key -> shifts.id, not null) -- ca làm việc của người dùng
    - timestamps

# bảng combos

    # note
    - bảng lưu combo sản phẩm.
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - name : (varchar(255), not null) -- tên combo sản phẩm
    - total_product : (unsigned int, not null) -- tổng sổ sản phẩm combo
    - total_cost : (decimal, not null) -- tổng giá sản phẩm gốc
    - total_combo_price : (decimal, not null) -- tổng giá sản phẩm combo
    - status : (tiny integer, not null) -- trạng thái sản phẩm
    - start_date : (timestamp, nullable) -- ngày bắt đầu áp dụng combo
    - end_date : (timestamp, nullable) -- ngày kết thúc áp dụng combo
    - updated_by : (unsignedBigInteger, nullable) -- người cập nhật cuối cùng
    - created_by : (unsignedBigInteger, nullable) -- người tạo mới
    - softDeletes
    - timestamps

# bảng combo_product

    # note
    - bảng pivot giữa combo và sản phẩm
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - combo_id : (int, foreign key -> combos.id, not null) -- combo sản phẩm
    - product_id : (int, foreign key -> shifts.id, not null) -- sản phẩm liên quan
    - quantity : (unsigned, integer, default 1) -- số lượng sản phẩm
    - price : (decimal, default 0) -- giá sản phẩm trong combo
    - timestamps
    - unique[[combo_id, product_id], combo_product_unique]

# bảng shipping_configs

    # note
    - bảng lưu trữ cấu hình giao hàng của tổ chức
    # cấu trúc
    - id: (int, primary key, auto-increment)
    - organization_id : (int, foreign key  -> organizations.id, not null) -- tổ chức có cấu hình
    - account_name : (varchar(255), not null) -- tài khoản ghn mà tổ chức liên kết giao hàng
    - api_token : (text, not null, encrypted ) -- token để gọi api đến hệ thống ghn
    - default_store_id : (varchar(255), not null) -- id cửa hàng của tài khoản đã liên kết
    - use_insurance : (boolean, default false) -- cấu hình mặc định khi tạo đơn có sử dụng bảo hiểm đơn hàng không
    - insurance_limit : (decimal, nullable) -- giá trị bảo hiểm hàng hóa khai báo khi lên đơn
    - required_note : (tiny integer, not null) -- yêu cầu note khi người dùng bắt đầu nhận hàng
    - allow_cod_on_failed : (boolean, default false) -- cho phép thu thêm khi giao thất bại
    - default_pick_shift : ( tiny integer, nullable) -- ca lấy hàng mong muốn
    - default_pickup_time : ( timestamp, nullable) -- thời gian lấy hàng mong muốn
    - timestamps
    - softDeletes
