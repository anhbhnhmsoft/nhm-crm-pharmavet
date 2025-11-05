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
