<?php

namespace App\Filament\Clusters\Accounting\Pages;

use App\Services\ReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
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
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->endOfMonth()->toDateString(),
            'type' => 'day',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('accounting.report.filter_section'))
                    ->schema([
                        DatePicker::make('from_date')
                            ->label(__('accounting.report.from_date'))
                            ->required()
                            ->default(now()->startOfMonth()),
                        DatePicker::make('to_date')
                            ->label(__('accounting.report.to_date'))
                            ->required()
                            ->default(now()->endOfMonth())
                            ->after('from_date'),
                        Select::make('type')
                            ->label(__('accounting.report.report_type'))
                            ->options([
                                'day' => __('accounting.report.type_day'),
                                'month' => __('accounting.report.type_month'),
                            ])
                            ->required()
                            ->default('day'),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function generateReport(): void
    {
        $data = $this->form->getState();
        $service = app(ReportService::class);
        $result = $service->getBusinessReport(
            organizationId: Auth::user()->organization_id,
            fromDate: $data['from_date'],
            toDate: $data['to_date'],
            type: $data['type']
        );

        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title(__('accounting.report.error_title'))
                ->body($result->getMessage())
                ->send();
        } else {
            $this->dispatch('report-generated', $result->getData());
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
}

