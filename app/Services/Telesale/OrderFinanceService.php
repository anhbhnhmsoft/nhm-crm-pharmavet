<?php

namespace App\Services\Telesale;

class OrderFinanceService
{
    public function calculateCollectAmount(array $inputs): array
    {
        $productTotal = (float) ($inputs['product_total'] ?? 0);
        $discount = (float) ($inputs['discount'] ?? 0);
        $ck1 = (float) ($inputs['ck1'] ?? 0);
        $ck2 = (float) ($inputs['ck2'] ?? 0);
        $shippingFee = (float) ($inputs['shipping_fee'] ?? 0);
        $codFee = (float) ($inputs['cod_fee'] ?? 0);
        $deposit = (float) ($inputs['deposit'] ?? 0);
        $codSupportAmount = (float) ($inputs['cod_support_amount'] ?? 0);

        $productDiscount = $productTotal * (($ck1 + $ck2) / 100);
        $totalDiscount = $productDiscount + $discount;
        $grossTotal = max(0, $productTotal - $totalDiscount + $shippingFee + $codFee);

        if ($deposit > $grossTotal) {
            throw new \RuntimeException(__('telesale.messages.deposit_exceeds_total'));
        }

        $collectAmount = max(0, $grossTotal - $deposit - $codSupportAmount);

        return [
            'gross_total' => round($grossTotal, 2),
            'product_discount' => round($productDiscount, 2),
            'total_discount' => round($totalDiscount, 2),
            'collect_amount' => round($collectAmount, 2),
        ];
    }
}
