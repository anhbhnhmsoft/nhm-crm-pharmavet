<?php

namespace App\Filament\Clusters\Accounting\Pages;

use App\Exports\FundLedgerExport;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Accounting\AccountingCluster;
use App\Models\Fund;
use App\Services\Accounting\FundLedgerReportService;
use App\Utils\Helper;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class FundLedgerReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = AccountingCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.clusters.accounting.pages.fund-ledger-report-page';

    public ?array $data = [];

    public array $summary = [];

    public array $compare = [];

    public array $rows = [];

    public static function getNavigationLabel(): string
    {
        return __('accounting.fund_ledger.navigation_label');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        if ($user->role === UserRole::SUPER_ADMIN->value) {
            return true;
        }

        return Helper::checkPermission([
            UserRole::ADMIN->value,
            UserRole::ACCOUNTING->value,
        ], $user->role);
    }

    public function getTitle(): string
    {
        return __('accounting.fund_ledger.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
        ]);
        $this->generateReport();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('accounting.fund_ledger.filters'))
                    ->schema([
                        DatePicker::make('from_date')->label(__('accounting.report.from_date'))->required(),
                        DatePicker::make('to_date')->label(__('accounting.report.to_date'))->required()->afterOrEqual('from_date'),
                        Select::make('fund_id')
                            ->label(__('accounting.fund.label'))
                            ->options(function () {
                                return Fund::query()
                                    ->where('organization_id', Auth::user()->organization_id)
                                    ->pluck('id', 'id')
                                    ->toArray();
                            })
                            ->searchable(),
                        TextInput::make('counterparty_name')
                            ->label(__('accounting.fund_transaction.counterparty_name')),
                    ])
                    ->columns(4),
            ])
            ->statePath('data');
    }

    public function generateReport(): void
    {
        $filters = $this->buildFilters();
        /** @var FundLedgerReportService $service */
        $service = app(FundLedgerReportService::class);

        $collection = $service->getLedger($filters);
        $this->rows = $collection->toArray();
        $this->summary = $service->getSummary($filters);
        $this->compare = $service->getCompareWithPreviousPeriod($filters);
    }

    public function exportExcel()
    {
        $filters = $this->buildFilters();
        /** @var FundLedgerReportService $service */
        $service = app(FundLedgerReportService::class);
        $rows = $service->getLedger($filters);

        return Excel::download(new FundLedgerExport($rows), 'fund-ledger-' . now()->format('YmdHis') . '.xlsx');
    }

    public function exportPdf()
    {
        $filters = $this->buildFilters();
        /** @var FundLedgerReportService $service */
        $service = app(FundLedgerReportService::class);
        $rows = $service->getLedger($filters);
        $summary = $service->getSummary($filters);

        $pdf = Pdf::loadView('exports.fund-ledger-pdf', [
            'rows' => $rows,
            'summary' => $summary,
            'filters' => $filters,
        ]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'fund-ledger-' . now()->format('YmdHis') . '.pdf'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label(__('accounting.fund_ledger.export_excel'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action('exportExcel'),
            Action::make('export_pdf')
                ->label(__('accounting.fund_ledger.export_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->action('exportPdf'),
        ];
    }

    protected function buildFilters(): array
    {
        $state = $this->form->getState();

        return [
            'organization_id' => (int) Auth::user()->organization_id,
            'from_date' => (string) ($state['from_date'] ?? now()->startOfMonth()->toDateString()),
            'to_date' => (string) ($state['to_date'] ?? now()->toDateString()),
            'fund_id' => (int) ($state['fund_id'] ?? 0),
            'counterparty_name' => (string) ($state['counterparty_name'] ?? ''),
        ];
    }
}
