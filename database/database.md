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


