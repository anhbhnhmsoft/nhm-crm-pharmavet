<?php

namespace App\Filament\Clusters\Accounting\Pages;

use App\Services\ReportService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use App\Filament\Clusters\Accounting\AccountingCluster;

class ReceivableReport extends Page
{
    protected static ?string $cluster = AccountingCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected string $view = 'filament.clusters.accounting.pages.receivable-report';
    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('accounting.report.receivable_title');
    }

    public function getTitle(): string
    {
        return __('accounting.report.receivable_title');
    }

    public ?array $receivableData = [];

    public function mount(ReportService $service): void
    {
        $this->loadData($service);
    }

    public function loadData(ReportService $service): void
    {
        $organizationId = Auth::user()->organization_id;

        $result = $service->getReceivableReport($organizationId);

        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title(__('accounting.report.error_title'))
                ->body(__('accounting.report.error_body'))
                ->send();
        } else {
            $this->receivableData = $result->getData();
        }
    }
}
