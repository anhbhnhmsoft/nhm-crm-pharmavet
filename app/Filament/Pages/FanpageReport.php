<?php

namespace App\Filament\Pages;

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
use App\Common\Constants\User\UserRole;
use App\Utils\Helper;

class FanpageReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected string $view = 'filament.clusters.marketing.pages.fanpage-report';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.unit_marketing');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketing.report.fanpage_title');
    }

    public function getTitle(): string
    {
        return __('marketing.report.fanpage_title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::MARKETING->value,
            UserRole::SALE->value,
        ], $user->role);
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('marketing.report.filter_section'))
                    ->schema([
                        DatePicker::make('from_date')
                            ->label(__('marketing.report.from_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now()),

                        DatePicker::make('to_date')
                            ->label(__('marketing.report.to_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->afterOrEqual('from_date'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function generateReport(ReportService $service): void
    {
        $data = $this->form->getState();
        $organizationId = Auth::user()->organization_id;

        $fromDate = $data['from_date'];
        $toDate = $data['to_date'];

        $result = $service->getFanpageReport($organizationId, $fromDate, $toDate);

        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title(__('filament.common.error'))
                ->body(__('marketing.report.error_generate'))
                ->send();
        } else {
            $fanpageData = $result->getData();

            $this->dispatch('report-generated', $fanpageData);
            Notification::make()
                ->success()
                ->title(__('marketing.report.success_generate'))
                ->send();
        }
    }
}
