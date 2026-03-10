<?php

namespace App\Common\Constants\Order;

enum GhnOrderStatus: string
{
    case READY_TO_PICK = 'ready_to_pick';
    case PICKING = 'picking';
    case CANCEL = 'cancel';
    case MONEY_COLLECT_PICKING = 'money_collect_picking';
    case PICKED = 'picked';
    case STORING = 'storing';
    case TRANSPORTING = 'transporting';
    case SORTING = 'sorting';
    case DELIVERING = 'delivering';
    case MONEY_COLLECT_DELIVERING = 'money_collect_delivering';
    case DELIVERED = 'delivered';
    case DELIVERY_FAIL = 'delivery_fail';
    case WAITING_TO_RETURN = 'waiting_to_return';
    case RETURN = 'return';
    case RETURN_TRANSPORTING = 'return_transporting';
    case RETURN_SORTING = 'return_sorting';
    case RETURNING = 'returning';
    case RETURN_FAIL = 'return_fail';
    case RETURNED = 'returned';
    case EXCEPTION = 'exception';
    case DAMAGE = 'damage';
    case LOST = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::READY_TO_PICK => 'Mới tạo đơn',
            self::PICKING => 'Đang lấy hàng',
            self::CANCEL => 'Đã hủy',
            self::MONEY_COLLECT_PICKING => 'Shipper làm việc với người gửi',
            self::PICKED => 'Đã lấy hàng',
            self::STORING => 'Đang nhập kho phân loại',
            self::TRANSPORTING => 'Đang trung chuyển',
            self::SORTING => 'Đang phân loại',
            self::DELIVERING => 'Đang giao hàng',
            self::MONEY_COLLECT_DELIVERING => 'Shipper làm việc với người nhận',
            self::DELIVERED => 'Đã giao hàng',
            self::DELIVERY_FAIL => 'Giao thất bại',
            self::WAITING_TO_RETURN => 'Chờ hoàn hàng',
            self::RETURN => 'Đang chờ hoàn về shop',
            self::RETURN_TRANSPORTING => 'Đang trung chuyển hoàn',
            self::RETURN_SORTING => 'Đang phân loại hoàn',
            self::RETURNING => 'Đang hoàn về shop',
            self::RETURN_FAIL => 'Hoàn hàng thất bại',
            self::RETURNED => 'Đã hoàn hàng',
            self::EXCEPTION => 'Ngoại lệ',
            self::DAMAGE => 'Hư hỏng',
            self::LOST => 'Thất lạc',
        };
    }

    public static function getLabel(?string $state): string
    {
        if (empty($state)) {
            return '-';
        }

        if ($state === 'cancelled') {
            $state = self::CANCEL->value;
        }

        return self::tryFrom($state)?->label() ?? strtoupper($state);
    }

    public static function color(?string $state): string
    {
        if ($state === 'cancelled') {
            $state = self::CANCEL->value;
        }

        return match ($state) {
            self::READY_TO_PICK->value,
            self::PICKING->value,
            self::PICKED->value,
            self::STORING->value,
            self::TRANSPORTING->value,
            self::SORTING->value,
            self::DELIVERING->value,
            self::MONEY_COLLECT_PICKING->value,
            self::MONEY_COLLECT_DELIVERING->value => 'info',

            self::DELIVERED->value,
            self::RETURNED->value => 'success',

            self::DELIVERY_FAIL->value,
            self::WAITING_TO_RETURN->value,
            self::RETURN->value,
            self::RETURN_TRANSPORTING->value,
            self::RETURN_SORTING->value,
            self::RETURNING->value,
            self::RETURN_FAIL->value,
            self::EXCEPTION->value,
            self::DAMAGE->value,
            self::LOST->value,
            self::CANCEL->value => 'danger',

            default => 'gray',
        };
    }

    public static function toOptions(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
