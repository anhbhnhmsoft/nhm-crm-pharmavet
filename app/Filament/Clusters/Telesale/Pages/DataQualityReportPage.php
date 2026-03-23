<?php

namespace App\Filament\Clusters\Telesale\Pages;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Telesale\TelesaleCluster;
use App\Models\Customer;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class DataQualityReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected string $view = 'filament.clusters.telesale.pages.data-quality-report-page';
    protected static ?int $navigationSort = 13;
    protected static string|null $cluster = TelesaleCluster::class;

    public ?array $data = [];
    public array $stats = [];

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->subMonth()->toDateString(),
            'to_date' => now()->toDateString(),
        ]);
        $this->generateReport();
    }

    public static function getNavigationLabel(): string
    {
        return __('telesale.reports.data_quality_navigation');
    }

    public function getTitle(): string
    {
        return __('telesale.reports.data_quality_title');
    }

    public static function canAccess(): bool
    {
        return in_array(Auth::user()->role, [
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::SALE->value,
        ], true);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('marketing.report.filter_section'))
                    ->schema([
                        DatePicker::make('from_date')->label(__('telesale.filters.from_date'))->native(false),
                        DatePicker::make('to_date')->label(__('telesale.filters.to_date'))->native(false),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function generateReport(): void
    {
        $state = $this->form->getState();
        $user = Auth::user();

        $query = Customer::query()->whereBetween('created_at', [
            ($state['from_date'] ?? now()->subMonth()->toDateString()) . ' 00:00:00',
            ($state['to_date'] ?? now()->toDateString()) . ' 23:59:59',
        ]);

        if ($user->role !== UserRole::SUPER_ADMIN->value) {
            $query->where('organization_id', $user->organization_id);
        }

        $duplicateByType = (clone $query)
            ->whereIn('customer_type', [2, 3])
            ->count();

        $total = (clone $query)->count();

        $this->stats = [
            'total_contacts' => $total,
            'duplicate_contacts' => $duplicateByType,
            'unique_contacts' => max(0, $total - $duplicateByType),
        ];
    }
}
