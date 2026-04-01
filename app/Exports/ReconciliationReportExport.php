<?php

namespace App\Exports;

use App\Exports\Sheets\ReconciliationDetailSheet;
use App\Exports\Sheets\ReconciliationSummarySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReconciliationReportExport implements WithMultipleSheets
{
    protected $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    public function sheets(): array
    {
        return [
            new ReconciliationDetailSheet($this->rows),
            new ReconciliationSummarySheet($this->rows),
        ];
    }
}
