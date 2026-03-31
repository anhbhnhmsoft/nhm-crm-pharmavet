<?php

namespace App\Filament\Clusters\Organization\Resources\Organizations\Schemas;

use App\Common\Constants\Organization\ProductField;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('organization.form.general_info'))
                ->description(__('organization.form.general_info_desc'))
                ->compact()
                ->schema([
                    Grid::make(2)->schema([
                        // Name
                        TextInput::make('name')
                            ->label(__('organization.form.name'))
                            ->placeholder(__('organization.form.name_placeholder'))
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->rules(['required', 'min:3', 'max:255'])
                            ->live(debounce: 1000)
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (blank($state)) return;
                                $slug = Str::of($state)
                                    ->ascii()
                                    ->upper()
                                    ->replace(' ', '');
                                if (!preg_match('/[0-9]/', $slug)) {
                                    $slug = $slug->append(substr(time(), -4));
                                }
                                $set('code', $slug->limit(20, '')->toString());
                            })
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min'      => __('common.error.min_length', ['min' => 3]),
                                'max'      => __('common.error.max_length', ['max' => 255]),
                            ]),

                        TextInput::make('code')
                            ->label(__('organization.form.code'))
                            ->required()
                            ->disabled(fn($livewire) => $livewire instanceof EditRecord)
                            ->extraInputAttributes(['required' => false])
                            ->maxLength(20)
                            // Cập nhật Regex: Phải có ít nhất 1 chữ số, còn lại là A-Z, 0-9, gạch ngang/dưới
                            ->rules([
                                'required',
                                'min:3',
                                'max:20',
                                'regex:/^(?=.*[0-9])[A-Z0-9_-]+$/',
                            ])
                            ->unique(ignoreRecord: true)
                            ->placeholder(__('organization.form.code_placeholder'))
                            ->helperText(__('organization.form.code_auto_note'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (blank($state)) return;

                                // 1. Làm sạch cơ bản: Bỏ dấu, Viết hoa, Bỏ cách
                                $processed = Str::of($state)
                                    ->ascii()
                                    ->upper()
                                    ->replace(' ', '');

                                // 2. Kiểm tra nếu CHƯA có số thì tự động Gen thêm số
                                // Ở đây tôi dùng 4 số cuối của timestamp để đảm bảo tính duy nhất và ngắn gọn
                                if (!preg_match('/[0-9]/', $processed)) {
                                    $processed = $processed->append(substr(time(), -4));
                                }

                                // 3. Cắt đúng 20 ký tự
                                $final = $processed->limit(20, '')->toString();

                                $set('code', $final);
                            })
                            ->dehydrateStateUsing(fn ($state) => Str::of($state)
                                ->ascii()
                                ->upper()
                                ->replace(' ', '')
                                ->limit(20, '')
                                ->toString())
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min'      => __('common.error.min_length', ['min' => 3]),
                                'max'      => __('common.error.max_length', ['max' => 20]),
                                'unique'   => __('organization.error.unique_code'),
                                'regex'    => __('organization.error.invalid_code') . ' ',
                            ]),

                        TextInput::make('phone')
                            ->label(__('organization.form.phone'))
                            ->placeholder(__('organization.form.phone_placeholder'))
                            ->maxLength(15)
                            ->rules([
                                'regex:/^(0|(\+84))[3|5|7|8|9][0-9]{8}$/',
                            ])
                            ->validationMessages([
                                'regex' => __('common.error.phone_invalid'), // VD: Số điện thoại không đúng định dạng VN.
                                'max'   => __('common.error.max_length', ['max' => 15]),
                            ]),

                        TextInput::make('address')
                            ->label(__('organization.form.address'))
                            ->maxLength(255)
                            ->placeholder(__('organization.form.address_placeholder'))
                            ->validationMessages([
                                'max'      => __('common.error.max_length', ['max' => 255]),
                            ]),
                    ]),
                ]),

            Section::make(__('organization.form.business_info'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('product_field')
                            ->label(__('organization.form.product_field'))
                            ->options(ProductField::toOptions())
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->searchable()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        TextInput::make('maximum_employees')
                            ->label(__('organization.form.maximum_employees'))
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->rule([
                                'required',
                                'numeric',
                                'min:1',
                                'max:9999',
                            ])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'min'      => __('common.error.min_value', ['min' => 1]),
                                'max'      => __('common.error.max_value', ['max' => 9999]),
                                'numeric'  => __('common.error.numeric'),
                            ]),
                        Toggle::make('is_foreign')
                            ->label(__('organization.form.is_foreign'))
                            ->default(false)
                            ->dehydrated(true),
                    ]),

                    Textarea::make('description')
                        ->label(__('organization.form.description'))
                        ->rows(4)
                        ->columnSpanFull()
                        ->placeholder(__('organization.form.description_placeholder')),
                ]),
        ]);
    }
}
