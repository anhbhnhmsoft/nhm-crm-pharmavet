<?php

namespace App\Filament\Clusters\Accounting\Pages;

use App\Exports\FundLedgerExport;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Accounting\AccountingCluster;
use App\Models\Fund;
use App\Services\Accounting\FundLedgerReportService;
use App\Services\ExportService;
use App\Utils\Helper;
use BackedEnum;
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
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                        DatePicker::make('from_date')
                            ->label(__('accounting.report.from_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('accounting.report.from_date'),
                                ]),
                            ]),
                        DatePicker::make('to_date')
                            ->label(__('accounting.report.to_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('from_date')
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('accounting.report.to_date'),
                                ]),
                                'after_or_equal' => __('accounting.fund_ledger.validation.invalid_date_range'),
                            ]),
                        Select::make('fund_id')
                            ->label(__('accounting.fund.label'))
                            ->options(fn (): array => $this->getFundOptions())
                            ->searchable()
                            ->native(false),
                        TextInput::make('counterparty_name')
                            ->label(__('accounting.fund_transaction.counterparty_name')),
                    ])
                    ->columns(4),
            ])
            ->statePath('data');
    }

    public function generateReport(): void
    {
        $filters = $this->getValidatedFilters();
        /** @var FundLedgerReportService $service */
        $service = app(FundLedgerReportService::class);

        $collection = $service->getLedger($filters);
        $this->rows = $this->formatLedgerRows($collection);
        $this->summary = $service->getSummary($filters);
        $this->compare = $service->getCompareWithPreviousPeriod($filters);
    }

    public function exportExcel()
    {
        $filters = $this->getValidatedFilters();
        /** @var FundLedgerReportService $service */
        $service = app(FundLedgerReportService::class);
        $rows = $service->getLedger($filters);

        return Excel::download(new FundLedgerExport($rows), 'fund-ledger-' . now()->format('YmdHis') . '.xlsx');
    }

    public function exportPdf(ExportService $exportService)
    {
        $filters = $this->getValidatedFilters();
        $payload = $this->buildPdfPayload($filters);
        $pdfContent = $exportService->generatePdfContent(
            'exports.fund-ledger-pdf',
            [
                'rows' => $payload['rows'],
                'summary' => $payload['summary'],
                'filters' => $filters,
            ],
            'a4',
            'landscape'
        );

        return response()->streamDownload(
            fn () => print($pdfContent),
            'fund-ledger-' . now()->format('YmdHis') . '.pdf',
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label(__('accounting.fund_ledger.export_excel'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->exportExcel()),
            Action::make('export_pdf')
                ->label(__('accounting.fund_ledger.export_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn (ExportService $exportService): StreamedResponse => $this->exportPdf($exportService)),
        ];
    }

    protected function buildPdfPayload(array $filters): array
    {
        /** @var FundLedgerReportService $service */
        $service = app(FundLedgerReportService::class);

        return [
            'rows' => $service->getLedger($filters)
                ->map(fn ($row) => [
                    'transaction_date' => optional($row->transaction_date)->toDateString(),
                    'transaction_code' => (string) ($row->transaction_code ?? ''),
                    'purpose' => (string) ($row->purpose ?? ''),
                    'counterparty_name' => (string) ($row->counterparty_name ?? ''),
                    'type' => (int) ($row->type ?? 0),
                    'amount' => (float) ($row->amount ?? 0),
                    'currency' => (string) ($row->currency ?? 'VND'),
                    'balance_after' => (float) ($row->balance_after ?? 0),
                    'note' => (string) ($row->note ?? ''),
                ])
                ->all(),
            'summary' => $service->getSummary($filters),
        ];
    }

    protected function getValidatedFilters(): array
    {
        $validated = $this->validate(
            [
                'data.from_date' => ['bail', 'required', 'date'],
                'data.to_date' => ['bail', 'required', 'date', 'after_or_equal:data.from_date'],
                'data.fund_id' => ['nullable'],
                'data.counterparty_name' => ['nullable', 'string'],
            ],
            [
                'data.to_date.after_or_equal' => __('accounting.fund_ledger.validation.invalid_date_range'),
            ],
            $this->getValidationAttributes(),
        );

        $data = $validated['data'];

        return [
            'organization_id' => (int) Auth::user()->organization_id,
            'from_date' => (string) $data['from_date'],
            'to_date' => (string) $data['to_date'],
            'fund_id' => (int) ($data['fund_id'] ?? 0),
            'counterparty_name' => trim((string) ($data['counterparty_name'] ?? '')),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'data.from_date' => __('accounting.report.from_date'),
            'data.to_date' => __('accounting.report.to_date'),
            'data.fund_id' => __('accounting.fund.label'),
            'data.counterparty_name' => __('accounting.fund_transaction.counterparty_name'),
        ];
    }

    protected function getFundOptions(): array
    {
        $organizationId = (int) (Auth::user()->organization_id ?? 0);

        return Fund::query()
            ->where('organization_id', $organizationId)
            ->orderBy('fund_type')
            ->orderBy('currency')
            ->orderBy('id')
            ->get(['id', 'fund_type', 'currency'])
            ->mapWithKeys(function (Fund $fund): array {
                $fundType = __('accounting.fund.fund_types.' . ($fund->fund_type ?: 'cash'));
                $currency = strtoupper((string) ($fund->currency ?: 'VND'));

                return [
                    $fund->id => "{$fundType} - {$currency} (#{$fund->id})",
                ];
            })
            ->all();
    }

    protected function formatLedgerRows(Collection $collection): array
    {
        return $collection
            ->map(fn ($row) => [
                'transaction_date' => optional($row->transaction_date)->toDateString(),
                'transaction_code' => (string) ($row->transaction_code ?? ''),
                'counterparty_name' => (string) ($row->counterparty_name ?? ''),
                'type' => (int) ($row->type ?? 0),
                'amount' => (float) ($row->amount ?? 0),
                'currency' => (string) ($row->currency ?? 'VND'),
                'balance_after' => (float) ($row->balance_after ?? 0),
                'description' => (string) ($row->description ?? ''),
            ])
            ->all();
    }
}
