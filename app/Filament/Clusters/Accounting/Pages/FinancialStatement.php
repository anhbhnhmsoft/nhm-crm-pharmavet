<?php

namespace App\Filament\Clusters\Accounting\Pages;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Services\ReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Common\Constants\GateKey;
use BackedEnum;

class FinancialStatement extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.clusters.accounting.pages.financial-statement';
    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('accounting.report.financial_statement.navigation_label');
    }

    public function getTitle(): string
    {
        return __('accounting.report.financial_statement.title');
    }

    public static function canAccess(): bool
    {
        return Gate::allows(GateKey::IS_CHIEF_ACCOUNTANT->value);
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'type' => 'month',
            'from_month' => now()->format('m'),
            'from_year' => now()->year,
            'to_month' => now()->format('m'),
            'to_year' => now()->year,
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('accounting.report.filter_section'))
                    ->schema([
                        Select::make('type')
                            ->label(__('accounting.report.report_type'))
                            ->options([
                                'day' => __('accounting.report.type_day'),
                                'month' => __('accounting.report.type_month'),
                            ])
                            ->required()
                            ->default('month')
                            ->live(),

                        DatePicker::make('from_date')
                            ->label(__('accounting.report.from_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->visible(fn(Get $get) => $get('type') === 'day'),

                        DatePicker::make('to_date')
                            ->label(__('accounting.report.to_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('from_date')
                            ->visible(fn(Get $get) => $get('type') === 'day'),

                        Section::make()
                            ->schema([
                                Select::make('from_month')
                                    ->label(__('accounting.report.from_month'))
                                    ->options(array_combine(range(1, 12), array_map(fn($m) => str_pad($m, 2, '0', STR_PAD_LEFT), range(1, 12))))
                                    ->required()
                                    ->columns(1),
                                TextInput::make('from_year')
                                    ->label(__('accounting.report.from_year'))
                                    ->numeric()
                                    ->required()
                                    ->columns(1),
                            ])
                            ->columns(2)
                            ->columnSpan(1)
                            ->visible(fn(Get $get) => $get('type') === 'month'),

                        Section::make()
                            ->schema([
                                Select::make('to_month')
                                    ->label(__('accounting.report.to_month'))
                                    ->options(array_combine(range(1, 12), array_map(fn($m) => str_pad($m, 2, '0', STR_PAD_LEFT), range(1, 12))))
                                    ->required()
                                    ->columns(1),
                                TextInput::make('to_year')
                                    ->label(__('accounting.report.to_year'))
                                    ->numeric()
                                    ->required()
                                    ->columns(1),
                            ])
                            ->columns(2)
                            ->columnSpan(1)
                            ->visible(fn(Get $get) => $get('type') === 'month'),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function generateReport(ReportService $service): void
    {
        $data = $this->form->getState();
        $organizationId = Auth::user()->organization_id;

        $type = $data['type'];
        $fromDate = null;
        $toDate = null;

        if ($type === 'day') {
            $fromDate = $data['from_date'];
            $toDate = $data['to_date'];
        } else {
            $fromMonth = str_pad($data['from_month'], 2, '0', STR_PAD_LEFT);
            $fromDate = "{$data['from_year']}-{$fromMonth}-01";
            $toMonth = (int) $data['to_month'];
            $toDate = now()->setYear((int) $data['to_year'])->setMonth($toMonth)->endOfMonth()->toDateString();

            $fromTS = strtotime($fromDate);
            $toTS = strtotime($toDate);

            if ($fromTS > $toTS) {
                Notification::make()
                    ->danger()
                    ->title(__('accounting.report.error_title'))
                    ->body(__('accounting.report.error_message'))
                    ->send();
                return;
            }

            $currentMonthStart = now()->startOfMonth()->timestamp;
            $selectedMonthStart = now()->setYear((int) $data['to_year'])->setMonth($toMonth)->startOfMonth()->timestamp;

            if ($selectedMonthStart > $currentMonthStart) {
                Notification::make()
                    ->danger()
                    ->title(__('accounting.report.error_title'))
                    ->body(__('accounting.report.error_exceed_time'))
                    ->send();
                return;
            }
        }

        $result = $service->getFinancialStatementReport(
            organizationId: $organizationId,
            fromDate: $fromDate,
            toDate: $toDate
        );

        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title(__('accounting.report.error_title'))
                ->body($result->getMessage())
                ->send();
        } else {
            $this->dispatch('financial-report-generated', $result->getData());
            Notification::make()
                ->success()
                ->title(__('accounting.report.success_title'))
                ->send();
        }
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.unit_accounting');
    }

    protected function getViewData(): array
    {
        return [
            'expenseCategories' => ExpenseCategory::getOptions(),
        ];
    }
}
