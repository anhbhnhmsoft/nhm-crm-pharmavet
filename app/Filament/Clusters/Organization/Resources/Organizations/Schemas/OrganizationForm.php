<?php

namespace App\Filament\Clusters\Organization\Resources\Organizations\Schemas;

use App\Common\Constants\Organization\ProductField;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament.organization.form.general_info'))
                ->description(__('filament.organization.form.general_info_desc'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label(__('filament.organization.form.name'))
                            ->required()
                            ->minLength(3)
                            ->maxLength(255)
                            ->placeholder(__('filament.organization.form.name_placeholder'))
                            ->live(debounce: 1000)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $slug = Str::slug($state, '-');
                                    $set('code', Str::upper($slug));
                                }
                            })
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min'      => __('common.error.min_length', ['min' => 3]),
                                'max'      => __('common.error.max_length', ['max' => 255]),
                            ]),

                        TextInput::make('code')
                            ->label(__('filament.organization.form.code'))
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->placeholder(__('filament.organization.form.code_placeholder'))
                            ->hintIcon('heroicon-m-question-mark-circle')
                            ->belowContent(__('filament.organization.form.code_auto_note'))
                            ->reactive()
                            ->debounce(500)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $slug = Str::slug($state, '-');
                                    $set('code', Str::upper($slug));
                                }
                            })
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max'      => __('common.error.max_length', ['max' => 20]),
                                'unique'   => __('common.error.unique'),
                            ]),

                        TextInput::make('phone')
                            ->label(__('filament.organization.form.phone'))
                            ->tel(true)
                            ->maxLength(20)
                            ->placeholder(__('filament.organization.form.phone_placeholder'))
                            ->validationMessages([
                                'max'      => __('common.error.max_length', ['max' => 20]),
                            ]),

                        TextInput::make('address')
                            ->label(__('filament.organization.form.address'))
                            ->maxLength(255)
                            ->placeholder(__('filament.organization.form.address_placeholder'))
                            ->validationMessages([
                                'max'      => __('common.error.max_length', ['max' => 255]),
                            ]),
                    ]),
                ]),

            Section::make(__('filament.organization.form.business_info'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('product_field')
                            ->label(__('filament.organization.form.product_field'))
                            ->options(ProductField::toOptions())
                            ->required()
                            ->searchable()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        TextInput::make('maximum_employees')
                            ->label(__('filament.organization.form.maximum_employees'))
                            ->numeric()
                            ->default(99)
                            ->minValue(1)
                            ->maxValue(9999)
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min'      => __('common.error.min_value', ['min' => 1]),
                                'max'      => __('common.error.max_value', ['max' => 9999]),
                            ]),
                    ]),

                    Textarea::make('description')
                        ->label(__('filament.organization.form.description'))
                        ->rows(4)
                        ->columnSpanFull()
                        ->placeholder(__('filament.organization.form.description_placeholder')),

                    Section::make(__('filament.organization.form.status'))
                        ->visible(fn($livewire) => !($livewire instanceof CreateRecord))
                        ->schema([
                            Toggle::make('disable')
                                ->label(__('filament.organization.form.disable'))
                                ->default(false)
                                ->disabled()
                                ->dehydrated(true),
                        ])
                        ->headerActions([
                            Action::make('toggle_disable')
                                ->label(
                                    fn($get) => $get('disable')
                                        ? __('filament.organization.form.enable')
                                        : __('filament.organization.form.disable_action')
                                )
                                ->icon('heroicon-o-arrow-path')
                                ->requiresConfirmation()
                                ->modalHeading(__('filament.organization.form.confirm_change'))
                                ->modalDescription(
                                    fn($get) => $get('disable')
                                        ? __('filament.organization.form.enable_warning')
                                        : __('filament.organization.form.disable_warning')
                                )
                                ->action(function ($set, $get) {
                                    $set('disable', !$get('disable'));
                                })
                                ->color(fn($get) => $get('disable') ? 'primary' : 'danger'),
                        ])
                ]),
        ]);
    }
}
