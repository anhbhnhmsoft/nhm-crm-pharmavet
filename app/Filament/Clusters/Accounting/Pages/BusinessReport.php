<?php

namespace App\Filament\Clusters\Accounting\Pages;

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
use BackedEnum;

class BusinessReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected string $view = 'filament.clusters.accounting.pages.business-report';
    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('accounting.report.navigation_label');
    }

    public function getTitle(): string
    {
        return __('accounting.report.title');
    }

    public ?array $data = [];

    public function mount(): void
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
                            ->default('day')
                            ->live(),

                        // --- Chế độ lọc theo Ngày ---
                        DatePicker::make('from_date')
                            ->label(__('accounting.report.from_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(now()->setYear(1800)->startOfYear())
                            ->maxDate(now())
                            ->visible(fn(Get $get) => $get('type') === 'day'),

                        DatePicker::make('to_date')
                            ->label(__('accounting.report.to_date'))
                            ->required()
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
                                    ->columns(1),
                                TextInput::make('from_year')
                                    ->label(__('accounting.report.from_year'))
                                    ->numeric()
                                    ->required()
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
                                    ->columns(1),
                                TextInput::make('to_year')
                                    ->label(__('accounting.report.to_year'))
                                    ->numeric()
                                    ->required()
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

    public function generateReport(): void
    {
        $data = $this->form->getState();
        $service = app(ReportService::class);
        $organizationId = Auth::user()->organization_id;

        $type = $data['type'];
        $fromDate = null;
        $toDate = null;

        if ($type === 'day') {
            $fromDate = $data['from_date'];
            $toDate = $data['to_date'];
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
                    ->body('Thời gian kết thúc phải sau hoặc bằng thời gian bắt đầu')
                    ->send();
                return;
            }

            // Validate không quá ngày hiện hành
            if ($toTS > now()->timestamp) {
                Notification::make()
                    ->danger()
                    ->title(__('accounting.report.error_title'))
                    ->body('Thời gian không được vượt quá hiện tại')
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
                ->body('Có lỗi xảy ra khi tạo báo cáo')
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

    protected function getViewData(): array
    {
        return [
            'expenseCategories' => \App\Common\Constants\Accounting\ExpenseCategory::getOptions(),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.unit_accounting');
    }
}

