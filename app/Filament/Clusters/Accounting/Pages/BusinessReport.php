<?php

namespace App\Filament\Clusters\Accounting\Pages;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Services\ReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action;
use App\Models\AccountingPeriod;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use BackedEnum;

class BusinessReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected string $view = 'filament.clusters.accounting.pages.business-report';
    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('accounting.report.navigation_business_label');
    }

    public function getTitle(): string
    {
        return __('accounting.report.title');
    }

    public ?array $data = [];

    public function mount(ReportService $service): void
    {
        $this->form->fill([
            'type' => 'day',
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
            'from_month' => now()->format('m'),
            'from_year' => now()->year,
            'to_month' => now()->format('m'),
            'to_year' => now()->year,
        ]);

        $this->generateReport($service);
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
                            ->native(false)
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ])
                            ->default('day')
                            ->live(),

                        // --- Chế độ lọc theo Ngày ---
                        DatePicker::make('from_date')
                            ->label(__('accounting.report.from_date'))
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ])
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(now()->setYear(1800)->startOfYear())
                            ->maxDate(now())
                            ->visible(fn(Get $get) => $get('type') === 'day'),

                        DatePicker::make('to_date')
                            ->label(__('accounting.report.to_date'))
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'after_or_equal' => __('validation.after_or_equal', [
                                    'attribute' => __('accounting.report.to_date'),
                                    'date' => __('accounting.report.from_date'),
                                ]),
                            ])
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(now()->setYear(1800)->startOfYear())
                            ->maxDate(now())
                            ->afterOrEqual('from_date')
                            ->visible(fn(Get $get) => $get('type') === 'day'),

                        // --- Chế độ lọc theo Tháng ---
                        Section::make()
                            ->schema([
                                Select::make('from_month')
                                    ->label(__('accounting.report.from_month'))
                                    ->options(array_combine(range(1, 12), array_map(fn($m) => str_pad($m, 2, '0', STR_PAD_LEFT), range(1, 12))))
                                    ->required()
                                    ->extraInputAttributes(['required' => false])
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ])
                                    ->columns(1),
                                TextInput::make('from_year')
                                    ->label(__('accounting.report.from_year'))
                                    ->numeric()
                                    ->required()
                                    ->extraInputAttributes(['required' => false])
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ])
                                    ->minValue(1800)
                                    ->maxValue(now()->year)
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
                                    ->extraInputAttributes(['required' => false])
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ])
                                    ->columns(1),
                                TextInput::make('to_year')
                                    ->label(__('accounting.report.to_year'))
                                    ->numeric()
                                    ->required()
                                    ->extraInputAttributes(['required' => false])
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ])
                                    ->minValue(1800)
                                    ->maxValue(now()->year)
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
            $validated = $this->validate(
                [
                    'data.from_date' => ['bail', 'required', 'date'],
                    'data.to_date' => ['bail', 'required', 'date', 'after_or_equal:data.from_date'],
                ],
                [
                    'data.from_date.required' => __('common.error.required'),
                    'data.to_date.required' => __('common.error.required'),
                    'data.to_date.after_or_equal' => __('validation.after_or_equal', [
                        'attribute' => __('accounting.report.to_date'),
                        'date' => __('accounting.report.from_date'),
                    ]),
                ],
                [
                    'data.from_date' => __('accounting.report.from_date'),
                    'data.to_date' => __('accounting.report.to_date'),
                ],
            );

            $fromDate = $validated['data']['from_date'];
            $toDate = $validated['data']['to_date'];
        } else {
            // Chế độ Tháng: Ghép month/year và chuẩn hóa thành ngày đầu/cuối tháng
            $fromMonth = str_pad($data['from_month'], 2, '0', STR_PAD_LEFT);
            $fromDate = "{$data['from_year']}-{$fromMonth}-01";

            $toMonth = (int) $data['to_month'];
            $toDate = now()->setYear((int) $data['to_year'])->setMonth($toMonth)->endOfMonth()->toDateString();

            // Validate From <= To (Month version)
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

            // Validate không quá ngày hiện hành
            if ($toTS > now()->timestamp) {
                Notification::make()
                    ->danger()
                    ->title(__('accounting.report.error_title'))
                    ->body(__('accounting.report.error_exceed_time'))
                    ->send();
                return;
            }
        }

        $businessResult = $service->getBusinessReport(
            organizationId: $organizationId,
            fromDate: $fromDate,
            toDate: $toDate,
            type: $type
        );

        $salesResult = $service->getSalesReport(
            organizationId: $organizationId,
            fromDate: $fromDate,
            toDate: $toDate
        );

        $marketingResult = $service->getMarketingReport(
            organizationId: $organizationId,
            fromDate: $fromDate,
            toDate: $toDate
        );

        $customerResult = $service->getCustomerReport(
            organizationId: $organizationId,
            fromDate: $fromDate,
            toDate: $toDate
        );

        if ($businessResult->isError() || $salesResult->isError() || $marketingResult->isError() || $customerResult->isError()) {
            Notification::make()
                ->danger()
                ->title(__('accounting.report.error_title'))
                ->body(__('accounting.report.error_create_report'))
                ->send();
        } else {
            $reportData = [
                'business' => $businessResult->getData(),
                'sales' => $salesResult->getData(),
                'marketing' => $marketingResult->getData(),
                'customers' => $customerResult->getData(),
            ];
            $this->dispatch('report-generated', $reportData);
            Notification::make()
                ->success()
                ->title(__('accounting.report.success_title'))
                ->send();
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            //
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('closePeriod')
                ->label(__('accounting.report.close_period'))
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('accounting.report.close_period_heading'))
                ->modalDescription(__('accounting.report.close_period_description'))
                ->modalSubmitActionLabel(__('accounting.report.close_period_submit'))
                ->form([
                    Select::make('month')
                        ->label(__('accounting.report.month'))
                        ->options(array_combine(range(1, 12), array_map(fn($m) => "Tháng " . str_pad($m, 2, '0', STR_PAD_LEFT), range(1, 12))))
                        ->default(now()->subMonth()->month)
                        ->required()
                        ->extraInputAttributes(['required' => false])
                        ->validationMessages([
                            'required' => __('common.error.required'),
                        ]),
                    TextInput::make('year')
                        ->label(__('accounting.report.year'))
                        ->numeric()
                        ->default(now()->year)
                        ->required()
                        ->extraInputAttributes([
                            'type' => 'text',
                            'inputmode' => 'numeric',
                            'required' => false,
                            'min' => null,
                            'max' => null,
                            'step' => null,
                        ])
                        ->validationMessages([
                            'required' => __('common.error.required'),
                            'numeric' => __('common.error.numeric'),
                            'min' => __('common.error.min_value', ['min' => 1800]),
                            'max' => __('common.error.max_value', ['max' => now()->year]),
                        ]),
                    Textarea::make('note')
                        ->label(__('accounting.report.note'))
                        ->placeholder(__('accounting.report.note_placeholder'))
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $organizationId = Auth::user()->organization_id;
                    $month = (int) $data['month'];
                    $year = (int) $data['year'];

                    // Check if already closed
                    if (AccountingPeriod::isClosed($organizationId, $month, $year)) {
                        Notification::make()
                            ->warning()
                            ->title(__('accounting.report.period_locked'))
                            ->send();
                        return;
                    }

                    try {
                        $period = AccountingPeriod::firstOrNew([
                            'organization_id' => $organizationId,
                            'month' => $month,
                            'year' => $year,
                        ]);

                        if ($period->closed_at) {
                            Notification::make()
                                ->warning()
                                ->title(__('accounting.report.period_locked'))
                                ->send();
                            return;
                        }

                        $period->fill([
                            'closed_at' => now(),
                            'closed_by' => Auth::id(),
                            'note' => $data['note'],
                        ]);

                        $period->save();
                    } catch (UniqueConstraintViolationException) {
                        Notification::make()
                            ->warning()
                            ->title(__('accounting.report.period_locked'))
                            ->send();
                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title(__('accounting.report.period_locked_success'))
                        ->body(
                            __('accounting.report.period_locked_body', [
                                'month' => $month,
                                'year' => $year,
                            ])
                        )
                        ->send();
                }),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'expenseCategories' => ExpenseCategory::getOptions(),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.unit_accounting');
    }
}
