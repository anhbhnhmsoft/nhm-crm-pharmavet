<?php

namespace Tests\Unit\Telesale;

use App\Services\Telesale\OrderFinanceService;
use RuntimeException;
use Tests\TestCase;

class OrderFinanceServiceTest extends TestCase
{
    public function test_ck1_increases_total_discount(): void
    {
        $service = new OrderFinanceService();

        $result = $service->calculateCollectAmount([
            'product_total' => 1000,
            'ck1' => 1,
        ]);

        $this->assertSame(10.0, $result['product_discount']);
        $this->assertSame(10.0, $result['total_discount']);
        $this->assertSame(990.0, $result['gross_total']);
        $this->assertSame(990.0, $result['collect_amount']);
    }

    public function test_ck2_increases_total_discount(): void
    {
        $service = new OrderFinanceService();

        $result = $service->calculateCollectAmount([
            'product_total' => 1000,
            'ck2' => 1,
        ]);

        $this->assertSame(10.0, $result['product_discount']);
        $this->assertSame(10.0, $result['total_discount']);
        $this->assertSame(990.0, $result['gross_total']);
        $this->assertSame(990.0, $result['collect_amount']);
    }

    public function test_manual_discount_ck1_and_ck2_are_combined_into_total_discount(): void
    {
        $service = new OrderFinanceService();

        $result = $service->calculateCollectAmount([
            'product_total' => 1000,
            'discount' => 100,
            'ck1' => 1,
            'ck2' => 1,
        ]);

        $this->assertSame(20.0, $result['product_discount']);
        $this->assertSame(120.0, $result['total_discount']);
        $this->assertSame(880.0, $result['gross_total']);
        $this->assertSame(880.0, $result['collect_amount']);
    }

    public function test_negative_ck1_is_rejected(): void
    {
        $service = new OrderFinanceService();

        $this->expectException(RuntimeException::class);
        $service->calculateCollectAmount([
            'product_total' => 1000,
            'ck1' => -1,
        ]);
    }

    public function test_ck2_above_100_is_rejected(): void
    {
        $service = new OrderFinanceService();

        $this->expectException(RuntimeException::class);
        $service->calculateCollectAmount([
            'product_total' => 1000,
            'ck2' => 101,
        ]);
    }

    public function test_negative_deposit_is_rejected(): void
    {
        $service = new OrderFinanceService();

        $this->expectException(RuntimeException::class);
        $service->calculateCollectAmount([
            'product_total' => 1000,
            'deposit' => -1,
        ]);
    }

    public function test_negative_cod_fee_is_rejected(): void
    {
        $service = new OrderFinanceService();

        $this->expectException(RuntimeException::class);
        $service->calculateCollectAmount([
            'product_total' => 1000,
            'cod_fee' => -1,
        ]);
    }

    public function test_deposit_cannot_exceed_order_total(): void
    {
        $service = new OrderFinanceService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('telesale.messages.deposit_exceeds_total'));

        $service->calculateCollectAmount([
            'product_total' => 1000,
            'deposit' => 1001,
        ]);
    }

    public function test_deposit_and_cod_support_cannot_exceed_order_total(): void
    {
        $service = new OrderFinanceService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('telesale.messages.collect_adjustments_exceed_total'));

        $service->calculateCollectAmount([
            'product_total' => 1000,
            'deposit' => 700,
            'cod_support_amount' => 301,
        ]);
    }

    public function test_preview_clamps_invalid_deposit_to_keep_collect_amount_non_negative(): void
    {
        $service = new OrderFinanceService();

        $result = $service->calculatePreview([
            'product_total' => 1000,
            'deposit' => 5000,
        ]);

        $this->assertSame(1000.0, $result['gross_total']);
        $this->assertSame(0.0, $result['collect_amount']);
    }
}
