<?php

namespace App\Filament\Clusters\Organization\Resources\CategoryProducts\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    TextInput::make('name')
                        ->label(__('organization.category_products.label'))
                        ->required(),
                    TextInput::make('description')
                        ->label(__('organization.category_products.description')),
                    Placeholder::make('totalProducts')
                        ->label(__('organization.category_products.total_products')),
                ])->columns(2),
            ]);
    }
}
