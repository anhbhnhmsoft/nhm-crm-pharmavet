<?php

namespace App\Filament\Pages;

use App\Common\Constants\GateKey;
use App\Services\Accounting\DebtReconciliationService;
use App\Services\ExportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use BackedEnum;

class DebtReconciliation extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';
    protected static ?int $navigationSort = 10;
    protected string $view = 'filament.clusters.accounting.pages.debt-reconciliation';

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.unit_accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('accounting.debt_reconciliation.navigation_label');
    }

    public function getTitle(): string
    {
        return __('accounting.debt_reconciliation.title');
    }

    public static function canAccess(): bool
    {
//        return Gate::allows(GateKey::IS_ACCOUNTING->value);
        return true;
    }

    public ?array $data = [];
    public ?array $reportData = null;

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
            'partner_type' => 'customer',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('accounting.debt_reconciliation.filter_section'))
                    ->schema([
                        Select::make('partner_type')
                            ->label(__('accounting.debt_reconciliation.partner_type'))
                            ->options([
                                'customer' => __('accounting.debt_reconciliation.partner_customer'),
                                'logistics' => __('accounting.debt_reconciliation.partner_logistics'),
                            ])
                            ->required()
                            ->live(),
                        Select::make('customer_id')
                            ->label(__('accounting.debt_reconciliation.select_customer'))
                            ->searchable()
                            ->options(function (DebtReconciliationService $service) {
                                return $service->getCustomersForSelect(Auth::user()->organization_id);
                            })
                            ->getSearchResultsUsing(function (string $search, DebtReconciliationService $service) {
                                return $service->getCustomersForSelect(Auth::user()->organization_id, $search);
                            })
                            ->visible(fn ($get) => $get('partner_type') === 'customer')
                            ->required(fn ($get) => $get('partner_type') === 'customer'),
                        DatePicker::make('from_date')
                            ->label(__('accounting.debt_reconciliation.from_date'))
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->required(),
                        DatePicker::make('to_date')
                            ->label(__('accounting.debt_reconciliation.to_date'))
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->required(),
                    ])
                    ->columns(4)
            ])
            ->statePath('data');
    }

    public function generateReport(DebtReconciliationService $service): void
    {
        $formData = $this->form->getState();
        $organizationId = Auth::user()->organization_id;

        if ($formData['partner_type'] === 'customer') {
            $result = $service->getCustomerReconciliation(
                $formData['customer_id'],
                $formData['from_date'],
                $formData['to_date']
            );
        } else {
            $result = $service->getLogisticsReconciliation(
                $organizationId,
                $formData['from_date'],
                $formData['to_date']
            );
        }

        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title(__('accounting.report.error_title'))
                ->body($result->getMessage())
                ->send();
            $this->reportData = null;
        } else {
            $this->reportData = $result->getData();
        }
    }

    public function export(ExportService $exportService)
    {
        if (!$this->reportData) {
            return Notification::make()
                ->warning()
                ->title(__('accounting.report.error_title'))
                ->body(__('Không có dữ liệu để xuất'))
                ->send();
        }

        $partnerName = $this->reportData['partner']['name'] ?? __('accounting.debt_reconciliation.partner_logistics');

        $pdfContent = $exportService->generatePdfContent(
            'pdf.debt-reconciliation',
            [
                'report' => $this->reportData,
                'filters' => $this->data,
                'partner_name' => $partnerName,
            ]
        );

        return response()->streamDownload(
            fn () => print($pdfContent),
            'Bien-ban-doi-chieu-' . now()->format('Ymd_His') . '.pdf'
        );
    }
}
