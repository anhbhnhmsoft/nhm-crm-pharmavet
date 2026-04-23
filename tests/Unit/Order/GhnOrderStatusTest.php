<?php

namespace Tests\Unit\Order;

use App\Common\Constants\Order\GhnOrderStatus;
use Tests\TestCase;

class GhnOrderStatusTest extends TestCase
{
    public function test_normalize_returns_expected_code_for_known_status_values(): void
    {
        $this->assertSame(
            GhnOrderStatus::DELIVERING->value,
            GhnOrderStatus::normalize(GhnOrderStatus::DELIVERING->value)
        );

        $this->assertSame(
            GhnOrderStatus::DELIVERING->value,
            GhnOrderStatus::normalize(GhnOrderStatus::DELIVERING->label())
        );
    }

    public function test_legacy_not_posted_text_is_treated_as_not_posted(): void
    {
        $this->assertNull(GhnOrderStatus::normalize('Chua dang don'));
        $this->assertNull(GhnOrderStatus::normalize(__('order.table.not_posted')));
        $this->assertSame(__('order.table.not_posted'), GhnOrderStatus::resolveLabel('Chua dang don'));
    }
}
