<?php

namespace App\Filament\Clusters\Telesale\Pages;

use App\Common\Constants\User\UserRole;
use App\Models\ReportExportJob;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ReportExportHistoryPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected string $view = 'filament.clusters.telesale.pages.report-export-history-page';

    protected static ?int $navigationSort = 16;

    public ?array $data = [];

    public array $jobs = [];

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
            'report_type' => null,
            'status' => null,
        ]);

        $this->loadJobs();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_telesale');
    }

    public static function getNavigationLabel(): string
    {
        return __('telesale.reports.export_history_navigation');
    }

    public function getTitle(): string
    {
        return __('telesale.reports.export_history_title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('marketing.report.filter_section'))
                    ->schema([
                        DatePicker::make('from_date')
                            ->label(__('telesale.filters.from_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('to_date')
                            ->label(__('telesale.filters.to_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('from_date'),
                        Select::make('report_type')
                            ->label(__('telesale.reports.export_history_report'))
                            ->options($this->getReportTypeOptions())
                            ->placeholder(__('telesale.reports.export_history_all_reports')),
                        Select::make('status')
                            ->label(__('telesale.reports.export_history_status'))
                            ->options($this->getStatusOptions())
                            ->placeholder(__('telesale.reports.export_history_all_statuses')),
                    ])
                    ->columns(4),
            ])
            ->statePath('data');
    }

    public static function canAccess(): bool
    {
        return in_array(Auth::user()->role, [
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::SALE->value,
        ], true);
    }

    public function loadJobs(): void
    {
        $user = Auth::user();
        $filters = $this->getFilters();

        $query = ReportExportJob::query()
            ->where('user_id', $user->id);

        if (filled($filters['from_date'])) {
            $query->where('created_at', '>=', $this->resolveDateBoundary($filters['from_date'], true));
        }

        if (filled($filters['to_date'])) {
            $query->where('created_at', '<=', $this->resolveDateBoundary($filters['to_date'], false));
        }

        if (filled($filters['report_type'])) {
            $query->where('report_type', $filters['report_type']);
        }

        if (filled($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $this->jobs = $query
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (ReportExportJob $job): array => $this->mapJob($job))
            ->all();
    }

    public function applyFilters(): void
    {
        $this->validate(
            [
                'data.from_date' => ['nullable', 'date'],
                'data.to_date' => ['nullable', 'date', 'after_or_equal:data.from_date'],
                'data.report_type' => ['nullable', 'string'],
                'data.status' => ['nullable', 'string'],
            ],
            [],
            [
                'data.from_date' => __('telesale.filters.from_date'),
                'data.to_date' => __('telesale.filters.to_date'),
                'data.report_type' => __('telesale.reports.export_history_report'),
                'data.status' => __('telesale.reports.export_history_status'),
            ],
        );

        $this->loadJobs();
    }

    public function downloadExport(int $jobId)
    {
        $job = ReportExportJob::query()
            ->where('user_id', Auth::id())
            ->find($jobId);

        if (! $job) {
            Notification::make()
                ->title(__('telesale.reports.export_not_found'))
                ->danger()
                ->send();

            return null;
        }

        if (blank($job->file_path) || ! Storage::disk('local')->exists($job->file_path)) {
            Notification::make()
                ->title(__('telesale.reports.export_file_missing'))
                ->danger()
                ->send();

            return null;
        }

        return Storage::disk('local')->download($job->file_path, basename($job->file_path));
    }

    protected function mapJob(ReportExportJob $job): array
    {
        $filePath = (string) ($job->file_path ?? '');

        return [
            'id' => $job->id,
            'report_type' => (string) $job->report_type,
            'report_label' => $this->getReportLabel((string) $job->report_type),
            'status' => (string) $job->status,
            'status_label' => $this->getStatusLabel((string) $job->status),
            'status_color' => $this->getStatusColor((string) $job->status),
            'row_count' => (int) ($job->row_count ?? 0),
            'file_name' => $filePath !== '' ? basename($filePath) : null,
            'file_path' => $filePath,
            'can_download' => $filePath !== '' && Storage::disk('local')->exists($filePath),
            'error_message' => $job->error_message,
            'created_at' => $job->created_at?->format('H:i d/m/Y'),
            'completed_at' => $job->completed_at?->format('H:i d/m/Y'),
        ];
    }

    protected function getReportLabel(string $reportType): string
    {
        return match ($reportType) {
            'top_sale_ranking' => __('telesale.reports.top_sale_title'),
            'operation_funnel' => __('telesale.reports.funnel_title'),
            default => str($reportType)->replace('_', ' ')->title()->toString(),
        };
    }

    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => __('telesale.reports.export_status_pending'),
            'processing' => __('telesale.reports.export_status_processing'),
            'completed' => __('telesale.reports.export_status_completed'),
            'failed' => __('telesale.reports.export_status_failed'),
            default => $status,
        };
    }

    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'gray',
            'processing' => 'warning',
            'completed' => 'success',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    protected function getReportTypeOptions(): array
    {
        $baseOptions = [
            'top_sale_ranking' => __('telesale.reports.top_sale_title'),
            'operation_funnel' => __('telesale.reports.funnel_title'),
        ];

        $dynamicOptions = ReportExportJob::query()
            ->where('user_id', Auth::id())
            ->distinct()
            ->pluck('report_type')
            ->filter()
            ->mapWithKeys(fn (string $reportType): array => [
                $reportType => $this->getReportLabel($reportType),
            ])
            ->all();

        return [...$baseOptions, ...$dynamicOptions];
    }

    protected function getStatusOptions(): array
    {
        return [
            'pending' => __('telesale.reports.export_status_pending'),
            'processing' => __('telesale.reports.export_status_processing'),
            'completed' => __('telesale.reports.export_status_completed'),
            'failed' => __('telesale.reports.export_status_failed'),
        ];
    }

    protected function getFilters(): array
    {
        return [
            'from_date' => $this->data['from_date'] ?? null,
            'to_date' => $this->data['to_date'] ?? null,
            'report_type' => $this->data['report_type'] ?? null,
            'status' => $this->data['status'] ?? null,
        ];
    }

    protected function resolveDateBoundary(mixed $value, bool $isStart): Carbon
    {
        $date = $value instanceof Carbon
            ? $value->copy()
            : Carbon::parse((string) $value);

        return $isStart ? $date->startOfDay() : $date->endOfDay();
    }
}
