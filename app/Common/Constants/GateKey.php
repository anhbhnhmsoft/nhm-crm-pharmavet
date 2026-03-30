<?php

namespace App\Common\Constants;

enum GateKey
{
    /**
     * Kiểm tra người dùng có phải siêu quản trị viên không
     */
    case IS_SUPER_ADMIN;
    /**
     * Kiểm tra người dùng có phải quản trị viên không
     */
    case IS_ADMIN;
    /**
     * Kiểm tra người dùng có vai trò nhất định
     */
    case HAS_ROLE;
    /**
     *Kiểm tra người dùng có chức vụ nhất định
     */
    case HAS_POSITION;

//    case IS_CHIEF_ACCOUNTANT = 'is_chief_accountant'; // Kiểm tra người dùng có phải kế toán trưởng không
//    case IS_ACCOUNTING = 'is_accounting'; // Kiểm tra quyền truy cập module kế toán (Kế toán thường & trưởng)
}
