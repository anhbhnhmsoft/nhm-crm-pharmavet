<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $row['organization_id'] = Auth::user()->organization_id;
        return new Product([
            'organization_id'      => $row['organization_id'] ?? null,
            'name'                 => $row['name'] ?? null,
            'sku'                  => $row['sku'] ?? null,
            'unit'                 => $row['unit'] ?? null,
            'weight'               => $row['weight'] ?? null,
            'cost_price'           => $row['cost_price'] ?? null,
            'sale_price'           => $row['sale_price'] ?? null,
            'barcode'              => $row['barcode'] ?? null,
            'type'                 => $row['type'] ?? null,
            'length'               => $row['length'] ?? null,
            'width'                => $row['width'] ?? null,
            'height'               => $row['height'] ?? null,
            'quantity'             => $row['quantity'] ?? 0,
            'vat_rate'             => $row['vat_rate'] ?? 0,
            'is_business_product'  => filter_var($row['is_business_product'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
