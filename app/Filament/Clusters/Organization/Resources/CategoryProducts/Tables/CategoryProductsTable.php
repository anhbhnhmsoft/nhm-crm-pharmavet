<?php

namespace App\Filament\Clusters\Organization\Resources\CategoryProducts\Tables;

use Dom\Text;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('organization.category_products.label')),
                TextColumn::make('description')
                    ->label(__('organization.category_products.description')),
                TextColumn::make('totalProducts')
                    ->label(__('organization.category_products.total_products')),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
