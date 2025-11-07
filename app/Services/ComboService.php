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
        $totalProduct = 0;
        $totalCost = 0;
        $totalComboPrice = 0;

        try {
            foreach ($products as $item) {
                $product = $this->productRepository->find($item['product_id']);

                if (!$product) {
                    continue;
                }

                $quantity = $item['quantity'] ?? 0;
                $price = $item['price'] ?? 0;
                $costPrice = $product->cost_price ?? 0;

                // Tổng số lượng sản phẩm
                $totalProduct += $quantity;

                // Tổng giá gốc (cost_price * quantity)
                $totalCost += $costPrice * $quantity;

                // Tổng giá combo (price * quantity)
                $totalComboPrice += $price * $quantity;
            }
            return ServiceReturn::success([
                'total_product' => $totalProduct,
                'total_cost' => $totalCost,
                'total_combo_price' => $totalComboPrice,
            ]);
        } catch (Throwable $thr) {
            Log::error('Sync Data Combo' . $thr->getMessage());
            return ServiceReturn::error($thr->getMessage());
        };
    }
}
