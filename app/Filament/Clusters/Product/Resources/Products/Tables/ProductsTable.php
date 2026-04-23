<?php

namespace App\Filament\Clusters\Product\Resources\Products\Tables;

use App\Common\Constants\Organization\ProductField;
use App\Exports\SimpleArrayExport;
use App\Models\CategoryProduct;
use App\Services\ProductExcelService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization.name')
                    ->label(__('filament.product.organization'))
                    ->visible(fn() => Auth::user()->role === \App\Common\Constants\User\UserRole::SUPER_ADMIN->value)
                    ->searchable()
                    ->sortable()
                    ->size('sm'),
                TextColumn::make('name')
                    ->label(__('common.table.name'))
                    ->searchable(),
                TextColumn::make('categoryProduct.display_name')
                    ->label(__('filament.product.category'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('sku')
                    ->label('SKU')->wrap()
                    ->searchable(),
                TextColumn::make('unit')
                    ->label(__('common.table.unit'))
                    ->searchable(),
                TextColumn::make('weight')
                    ->label(__('filament.product.weight'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cost_price')
                    ->label(__('filament.product.cost_price'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sale_price')
                    ->label(__('filament.product.sale_price'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('filament.product.type'))
                    ->formatStateUsing(function ($state) {
                        return ProductField::getLabel( (int) $state);
                    })
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label(__('filament.product.quantity'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('vat_rate')
                    ->label(__('filament.product.vat_percent'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_business_product')
                    ->label(__('filament.product.business'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_product_id')
                    ->label(__('filament.product.category'))
                    ->options(fn() => CategoryProduct::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn(CategoryProduct $category) => [
                            $category->id => $category->display_name,
                        ])
                        ->all()),
                SelectFilter::make('type')
                    ->label(__('filament.product.type'))
                    ->options(ProductField::toOptions()),
                TrashedFilter::make()
                    ->label(__('common.table.trashed')),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label(__('common.action.view'))
                        ->tooltip(__('common.tooltip.view'))
                        ->icon('heroicon-o-eye'),

                    EditAction::make()
                        ->label(__('common.action.edit'))
                        ->tooltip(__('common.tooltip.edit'))
                        ->icon('heroicon-o-pencil-square'),

                    DeleteAction::make()
                        ->label(__('common.action.delete'))
                        ->tooltip(__('common.tooltip.delete'))
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.delete_title'))
                        ->modalDescription(__('common.modal.delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete'))
                        ->visible(fn($record) => !$record->trashed()),

                    RestoreAction::make()
                        ->label(__('common.action.restore'))
                        ->tooltip(__('common.tooltip.restore'))
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn($record) => $record->trashed()),
                ])
            ], position: \Filament\Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('common.action.delete'))
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.delete_title'))
                        ->modalDescription(__('common.modal.delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete')),

                    RestoreBulkAction::make()
                        ->label(__('common.action.restore'))
                        ->visible(fn($livewire) => $livewire->tableFilters['trashed']['value'] ?? null === 'only'),

                    ForceDeleteBulkAction::make()
                        ->label(__('common.action.force_delete'))
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.force_delete_title'))
                        ->modalDescription(__('common.modal.force_delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete')),
                ]),

                Action::make('export')
                    ->label(__('common.action.export_excel'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (ProductExcelService $service) {
                        $organizationId = Auth::user()->organization_id;
                        $products = $service->getExportProducts($organizationId);

                        if ($products->isEmpty()) {
                            Notification::make()
                                ->warning()
                                ->title(__('filament.product.export_empty'))
                                ->send();

                            return null;
                        }

                        return Excel::download(
                            new SimpleArrayExport(
                                $service->headings(),
                                $service->exportRows($products),
                            ),
                            'products_' . now()->format('Ymd_His') . '.xlsx'
                        );
                    }),

                Action::make('import')
                    ->label(__('common.action.import'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->form([
                        FileUpload::make('file')
                            ->label(__('common.action.upload_excel'))
                            ->helperText(__('filament.product.import_hint'))
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintAction(
                                Action::make('download-template')
                                    ->label(__('common.action.download_template'))
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('gray')
                                    ->action(function (ProductExcelService $service) {
                                        return Excel::download(
                                            new SimpleArrayExport(
                                                $service->headings(),
                                                $service->templateRows((int) Auth::user()->organization_id),
                                            ),
                                            'products_template.xlsx'
                                        );
                                    })
                            )
                            ->required()
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ]),
                    ])
                    ->action(function (array $data, ProductExcelService $service) {
                        /** @var TemporaryUploadedFile $file */
                        $file = $data['file'];
                        try {
                            $count = $service->importFile($file, (int) Auth::user()->organization_id);

                            Notification::make()
                                ->title(__('filament.product.import_success'))
                                ->body(__('filament.product.import_success_body', ['count' => $count]))
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title(__('filament.product.import_failed'))
                                ->body(self::formatImportErrorMessage($exception))
                                ->danger()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title(__('filament.product.import_failed'))
                                ->body(__('filament.product.import_unexpected_error'))
                                ->danger()
                                ->send();
                        }
                    })
            ]);
    }

    protected static function formatImportErrorMessage(ValidationException $exception): string
    {
        $messages = collect($exception->errors())
            ->flatten()
            ->filter(fn ($message) => filled($message))
            ->unique()
            ->values();

        return $messages->take(3)->implode(PHP_EOL) ?: __('filament.product.import_failed');
    }
}
