<?php

namespace App\Filament\Widgets;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

class SelectGap extends Widget implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?int $sort = 0;

    protected function getTitle(): string
    {
        return __('dashboard.select_gap.title_fund');
    }

    public ?string $start_date = null;
    public ?string $end_date = null;

    public function mount(): void
    {
        $this->start_date = session('dashboard_start_date', now()->startOfMonth()->format('Y-m-d'));
        $this->end_date = session('dashboard_end_date', now()->endOfMonth()->format('Y-m-d'));

        $this->schema->fill([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ]);
    }

    public static function getHeading(): string
    {
        return __('dashboard.select_gap.heading');
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    Grid::make()->columns(2)->schema([
                        DatePicker::make('start_date')
                            ->label(__('dashboard.select.start_date_label'))
                            ->live()
                            ->required()
                            ->maxDate(fn($get) => $get('end_date') ?: now())
                            ->validationMessages([
                                'required' => __('dashboard.select.start_date_required'),
                                'maxDate' => __('dashboard.select.start_date_max_date'),
                            ])
                            ->afterStateUpdated(function ($state) {
                                $this->start_date = $state;
                                session(['dashboard_start_date' => $state]);
                                $this->dispatch('dateRangeUpdated', [
                                    'start_date' => $state,
                                    'end_date' => $this->end_date,
                                ]);
                            })
                            ->columnSpan(1),

                        DatePicker::make('end_date')
                            ->label(__('dashboard.select.end_date_label'))
                            ->live()
                            ->required()
                            ->minDate(fn($get) => $get('start_date') ?: now())
                            ->validationMessages([
                                'required' => __('dashboard.select.end_date_required'),
                                'minDate' => __('dashboard.select.end_date_min_date'),
                            ])
                            ->afterStateUpdated(function ($state) {
                                $this->end_date = $state;
                                session(['dashboard_end_date' => $state]);
                                $this->dispatch('dateRangeUpdated', [
                                    'start_date' => $this->start_date,
                                    'end_date' => $state,
                                ]);
                            })
                            ->columnSpan(1),
                    ]),
                ])
            ]);
    }

    public string $view = 'filament.widgets.select-gap';
}
