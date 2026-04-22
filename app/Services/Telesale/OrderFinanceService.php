<?php

namespace App\Services\Telesale;

class OrderFinanceService
{
    public function calculateCollectAmount(array $inputs): array
    {
        $values = $this->normalizeInputs($inputs);
        $this->validateInputs($values);

        return $this->buildSummary($values, true);
    }

    public function calculatePreview(array $inputs): array
    {
        $values = $this->normalizeInputs($inputs);
        $values['discount'] = max(0, $values['discount']);
        $values['ck1'] = min(100, max(0, $values['ck1']));
        $values['ck2'] = min(100, max(0, $values['ck2']));
        $values['shipping_fee'] = max(0, $values['shipping_fee']);
        $values['cod_fee'] = max(0, $values['cod_fee']);
        $values['deposit'] = max(0, $values['deposit']);
        $values['cod_support_amount'] = max(0, $values['cod_support_amount']);

        return $this->buildSummary($values, false);
    }

    protected function normalizeInputs(array $inputs): array
    {
        return [
            'product_total' => (float) ($inputs['product_total'] ?? 0),
            'discount' => (float) ($inputs['discount'] ?? 0),
            'ck1' => (float) ($inputs['ck1'] ?? 0),
            'ck2' => (float) ($inputs['ck2'] ?? 0),
            'shipping_fee' => (float) ($inputs['shipping_fee'] ?? 0),
            'cod_fee' => (float) ($inputs['cod_fee'] ?? 0),
            'deposit' => (float) ($inputs['deposit'] ?? 0),
            'cod_support_amount' => (float) ($inputs['cod_support_amount'] ?? 0),
        ];
    }

    protected function validateInputs(array $values): void
    {
        $this->ensureNonNegative($values['product_total'], __('warehouse.order.form.total_amount'));
        $this->ensureNonNegative($values['discount'], __('warehouse.order.form.discount'));
        $this->ensurePercentage($values['ck1'], __('order.form.ck1'));
        $this->ensurePercentage($values['ck2'], __('order.form.ck2'));
        $this->ensureNonNegative($values['shipping_fee'], __('warehouse.order.form.shipping_fee'));
        $this->ensureNonNegative($values['cod_fee'], __('warehouse.order.form.cod_fee'));
        $this->ensureNonNegative($values['deposit'], __('warehouse.order.form.deposit'));
        $this->ensureNonNegative($values['cod_support_amount'], __('telesale.form.cod_support_amount'));
    }

    protected function ensureNonNegative(float $value, string $attribute): void
    {
        if ($value < 0) {
            throw new \RuntimeException(__('common.error.min.numeric', [
                'attribute' => $attribute,
                'min' => 0,
            ]));
        }
    }

    protected function ensurePercentage(float $value, string $attribute): void
    {
        $this->ensureNonNegative($value, $attribute);

        if ($value > 100) {
            throw new \RuntimeException(__('common.error.max.numeric', [
                'attribute' => $attribute,
                'max' => 100,
            ]));
        }
    }

    protected function buildSummary(array $values, bool $validateDeposit): array
    {
        $productDiscount = $values['product_total'] * (($values['ck1'] + $values['ck2']) / 100);
        $totalDiscount = $productDiscount + $values['discount'];
        $grossTotal = max(0, $values['product_total'] - $totalDiscount + $values['shipping_fee'] + $values['cod_fee']);

        if ($validateDeposit && $values['deposit'] > $grossTotal) {
            throw new \RuntimeException(__('telesale.messages.deposit_exceeds_total'));
        }

        $collectAmount = max(0, $grossTotal - $values['deposit'] - $values['cod_support_amount']);

        return [
            'gross_total' => round($grossTotal, 2),
            'product_discount' => round($productDiscount, 2),
            'total_discount' => round($totalDiscount, 2),
            'collect_amount' => round($collectAmount, 2),
        ];
    }
}
