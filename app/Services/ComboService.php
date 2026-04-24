<?php

namespace App\Services;

use App\Core\ServiceReturn;
use App\Repositories\ComboRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class ComboService
{
    protected ComboRepository $comboRepository;
    protected ProductRepository $productRepository;


    public function __construct(
        ComboRepository $comboRepository,
        ProductRepository $productRepository
    ) {
        $this->comboRepository = $comboRepository;
        $this->productRepository = $productRepository;
    }

    public function calculateTotals(array $products): ServiceReturn
    {
        return $this->summarize($products);
    }

    public function summarize(array $products): ServiceReturn
    {
        $totalProduct = 0;
        $totalCost = 0;
        $totalOriginalPrice = 0;
        $totalComboPrice = 0;

        try {
            foreach ($products as $item) {
                $productId = (int) ($item['product_id'] ?? 0);

                if ($productId <= 0) {
                    continue;
                }

                $product = $this->productRepository->find($productId);

                if (!$product) {
                    return ServiceReturn::error(__('common.error.not_exist'));
                }

                $quantity = (int) ($item['quantity'] ?? 0);
                $price = (float) ($item['price'] ?? 0);
                $costPrice = $product->cost_price ?? 0;
                $originalSalePrice = $product->sale_price ?? 0;

                // Tổng số lượng sản phẩm
                $totalProduct += $quantity;

                // Tổng giá gốc (cost_price * quantity)
                $totalCost += $costPrice * $quantity;

                // Tổng giá bán lẻ hiện tại của các sản phẩm
                $totalOriginalPrice += $originalSalePrice * $quantity;

                // Tổng giá combo (price * quantity)
                $totalComboPrice += $price * $quantity;
            }

            $savingsAmount = max($totalOriginalPrice - $totalComboPrice, 0);
            $savingsPercentage = $totalOriginalPrice > 0
                ? round(($savingsAmount / $totalOriginalPrice) * 100, 2)
                : 0;

            return ServiceReturn::success([
                'total_product' => $totalProduct,
                'total_cost' => $totalCost,
                'total_original_price' => $totalOriginalPrice,
                'total_combo_price' => $totalComboPrice,
                'savings_amount' => $savingsAmount,
                'savings_percentage' => $savingsPercentage,
            ]);
        } catch (Throwable $thr) {
            Log::error('Sync Data Combo' . $thr->getMessage());
            return ServiceReturn::error($thr->getMessage());
        };
    }

    public function validateComboPricing(array $products): ServiceReturn
    {
        $summary = $this->summarize($products);

        if ($summary->isError()) {
            return $summary;
        }

        $data = (array) $summary->getData();
        $totalComboPrice = (float) ($data['total_combo_price'] ?? 0);
        $totalOriginalPrice = (float) ($data['total_original_price'] ?? 0);

        if ($totalComboPrice <= 0) {
            return ServiceReturn::error(__('filament.combo.validation.combo_price_must_be_positive'));
        }

        if ($totalOriginalPrice <= 0) {
            return ServiceReturn::error(__('filament.combo.validation.original_price_must_be_positive'));
        }

        if ($totalComboPrice >= $totalOriginalPrice) {
            return ServiceReturn::error(__('filament.combo.validation.combo_price_must_be_less_than_original'));
        }

        return $summary;
    }
}
