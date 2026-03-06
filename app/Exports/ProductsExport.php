<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ProductsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting
{
    public function collection()
    {
        return Product::with(['organization'])
            ->get([
                'id',
                'organization_id',
                'name',
                'sku',
                'unit',
                'weight',
                'cost_price',
                'sale_price',
                'barcode',
                'type',
                'length',
                'width',
                'height',
                'quantity',
                'vat_rate',
                'is_business_product',
                'created_at',
                'updated_at',
            ]);
    }

    public function headings(): array
    {
        return [
            'ID',
            __('filament.product.name'),
            'SKU',
            __('filament.product.unit'),
            __('filament.product.weight'),
            __('filament.product.cost_price'),
            __('filament.product.sale_price'),
            __('filament.product.barcode'),
            __('filament.product.type'),
            __('filament.product.length'),
            __('filament.product.width'),
            __('filament.product.height'),
            __('filament.product.quantity'),
            __('filament.product.vat_percent'),
            __('filament.product.business'),
            __('common.table.created_at'),
            __('common.table.updated_at'),
        ];
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->name,
            $product->sku,
            $product->unit,
            $product->weight,
            $product->cost_price,
            $product->sale_price,
            $product->barcode,
            $product->type,
            $product->length,
            $product->width,
            $product->height,
            $product->quantity,
            $product->vat_rate,
            $product->is_business_product ? __('common.status.enabled') :  __('common.status.disabled'),
            optional($product->created_at)->format('d/m/Y H:i'),
            optional($product->updated_at)->format('d/m/Y H:i'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_NUMBER_00, // weight
            'G' => NumberFormat::FORMAT_NUMBER_00, // cost_price
            'H' => NumberFormat::FORMAT_NUMBER_00, // sale_price
            'N' => NumberFormat::FORMAT_NUMBER,    // quantity
            'O' => NumberFormat::FORMAT_PERCENTAGE, // vat_rate
        ];
    }
}