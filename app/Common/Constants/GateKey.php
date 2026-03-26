<?php

namespace App\Common\Constants;

enum GateKey:string
{
    case IS_SUPER_ADMIN = 'is_super_admin'; // Kiểm tra người dùng có phải siêu quản trị viên không
    case HAS_ROLE = 'has_role'; // Kiểm tra người dùng có vai trò nhất định
    case HAS_POSITION = 'has_position'; // Kiểm tra người dùng có chức vụ nhất định
    case IS_CHIEF_ACCOUNTANT = 'is_chief_accountant'; // Kiểm tra người dùng có phải kế toán trưởng không
    case IS_ACCOUNTING = 'is_accounting'; // Kiểm tra quyền truy cập module kế toán (Kế toán thường & trưởng)
}
