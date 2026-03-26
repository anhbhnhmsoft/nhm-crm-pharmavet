<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class ExportService
{
    /**
     * Xuất file PDF từ View và Dữ liệu
     */
    public function generatePdfContent(
        string $view,
        array $data,
        string $paper = 'a4',
        string $orientation = 'portrait'
    ): string {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper($paper, $orientation)
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);

        return $pdf->output();
    }
}
