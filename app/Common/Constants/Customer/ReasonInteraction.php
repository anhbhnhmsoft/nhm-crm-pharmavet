<?php

namespace App\Common\Constants\Customer;

enum ReasonInteraction: int
{
    case CLOSING_ORDER = 1;
    case NO_ANSWER = 2;
    case BUSY = 3;
    case CALL_BACK = 4;
    case SUBSCRIBERS = 5;
    case THINK_MORE = 6;
    case NO_NEED = 7;
    case GOOD_PERFORMANCE = 8;
    case POOR_PERFORMANCE = 9;

    public function label(): string
    {
        return match ($this) {
            self::CLOSING_ORDER => __('telesale.reason_interaction.closing_order'),
            self::NO_ANSWER => __('telesale.reason_interaction.no_answer'),
            self::BUSY => __('telesale.reason_interaction.busy'),
            self::CALL_BACK => __('telesale.reason_interaction.call_back'),
            self::SUBSCRIBERS => __('telesale.reason_interaction.subscribers'),
            self::THINK_MORE => __('telesale.reason_interaction.think_more'),
            self::NO_NEED => __('telesale.reason_interaction.no_need'),
            self::GOOD_PERFORMANCE => __('telesale.reason_interaction.good_performance'),
            self::POOR_PERFORMANCE => __('telesale.reason_interaction.poor_performance'),
        };
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }

    public static function getLabel(int $value): string
    {
        return match ($value) {
            self::CLOSING_ORDER => __('telesale.reason_interaction.closing_order'),
            self::NO_ANSWER => __('telesale.reason_interaction.no_answer'),
            self::BUSY => __('telesale.reason_interaction.busy'),
            self::CALL_BACK => __('telesale.reason_interaction.call_back'),
            self::SUBSCRIBERS => __('telesale.reason_interaction.subscribers'),
            self::THINK_MORE => __('telesale.reason_interaction.think_more'),
            self::NO_NEED => __('telesale.reason_interaction.no_need'),
            self::GOOD_PERFORMANCE => __('telesale.reason_interaction.good_performance'),
            self::POOR_PERFORMANCE => __('telesale.reason_interaction.poor_performance'),
        };
    }

    /**
     * Determine the next interaction status based on the reason and current status
     * 
     * @param int $reasonValue The reason interaction value
     * @param int $currentStatus The current interaction status
     * @return int The next interaction status
     */
    public static function getNextStatus(int $reasonValue, int $currentStatus): int
    {
        return match ($reasonValue) {
            // Dứt điểm - Thành công
            self::CLOSING_ORDER->value,
            self::GOOD_PERFORMANCE->value => \App\Common\Constants\Interaction\InteractionStatus::RECEIVED->value,

            // Dứt điểm - Thất bại
            self::NO_NEED->value => \App\Common\Constants\Interaction\InteractionStatus::UN_CARE->value,
            self::SUBSCRIBERS->value,
            self::POOR_PERFORMANCE->value => \App\Common\Constants\Interaction\InteractionStatus::INEFFICIENT->value,

            // Phụ thuộc - Lên lịch chăm sóc
            self::CALL_BACK->value,
            self::THINK_MORE->value => \App\Common\Constants\Interaction\InteractionStatus::SECOND_CARE->value,

            // Tiếp tuyến - Gọi lại theo quy trình
            self::NO_ANSWER->value,
            self::BUSY->value => self::getNextCallStatus($currentStatus),

            default => $currentStatus, // Giữ nguyên nếu không match
        };
    }

    /**
     * Get the next call status in the sequence
     * 
     * @param int $currentStatus Current interaction status
     * @return int Next call status
     */
    private static function getNextCallStatus(int $currentStatus): int
    {
        return match ($currentStatus) {
            \App\Common\Constants\Interaction\InteractionStatus::FIRST_CALL->value =>
            \App\Common\Constants\Interaction\InteractionStatus::SECOND_CALL->value,
            \App\Common\Constants\Interaction\InteractionStatus::SECOND_CALL->value =>
            \App\Common\Constants\Interaction\InteractionStatus::THIRD_CALL->value,
            \App\Common\Constants\Interaction\InteractionStatus::THIRD_CALL->value =>
            \App\Common\Constants\Interaction\InteractionStatus::FOURTH_CALL->value,
            \App\Common\Constants\Interaction\InteractionStatus::FOURTH_CALL->value =>
            \App\Common\Constants\Interaction\InteractionStatus::FIFTH_CALL->value,
            \App\Common\Constants\Interaction\InteractionStatus::FIFTH_CALL->value =>
            \App\Common\Constants\Interaction\InteractionStatus::SIXTH_CALL->value,
            \App\Common\Constants\Interaction\InteractionStatus::SIXTH_CALL->value =>
            \App\Common\Constants\Interaction\InteractionStatus::USER_MANUAL->value,
            \App\Common\Constants\Interaction\InteractionStatus::USER_MANUAL->value =>
            \App\Common\Constants\Interaction\InteractionStatus::SECOND_CARE->value,
            \App\Common\Constants\Interaction\InteractionStatus::SECOND_CARE->value =>
            \App\Common\Constants\Interaction\InteractionStatus::THIRD_CARE->value,
            \App\Common\Constants\Interaction\InteractionStatus::THIRD_CARE->value =>
            \App\Common\Constants\Interaction\InteractionStatus::RECEIVED->value,
            default => $currentStatus,
        };
    }

    /**
     * Check if this reason requires scheduling a callback
     * 
     * @param int $reasonValue The reason interaction value
     * @return bool True if requires scheduling
     */
    public static function requiresScheduling(int $reasonValue): bool
    {
        return in_array($reasonValue, [
            self::CALL_BACK->value,
            self::THINK_MORE->value,
        ]);
    }
}
