<?php

namespace App\Common\Constants;

enum GateKey:string
{
    case IS_SUPER_ADMIN = 'is_super_admin'; // Kiểm tra người dùng có phải siêu quản trị viên không
    case HAS_ROLE = 'has_role'; // Kiểm tra người dùng có vai trò nhất định
    case HAS_POSITION = 'has_position'; // Kiểm tra người dùng có chức vụ nhất định

}
