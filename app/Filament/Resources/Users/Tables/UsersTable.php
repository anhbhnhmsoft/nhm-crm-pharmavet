<?php

namespace App\Filament\Resources\Users\Tables;

use App\Common\Constants\User\UserPosition;
use App\Common\Constants\User\UserRole;
use App\Utils\Helper;
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
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Support\Facades\Auth;
use STS\FilamentImpersonate\Actions\Impersonate;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.user.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('username')
                    ->label(__('filament.user.username'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('filament.user.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label(__('filament.user.phone'))
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('role')
                    ->label(__('filament.user.role'))
                    ->sortable()
                    ->formatStateUsing(fn($state) => UserRole::getLabel((int) $state)),

                TextColumn::make('position')
                    ->label(__('filament.user.position'))
                    ->sortable()
                    ->formatStateUsing(fn($state) => UserPosition::getLabel((int) $state)),


                IconColumn::make('disable')
                    ->boolean()
                    ->label(__('filament.user.disable'))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                TextColumn::make('last_login_at')
                    ->label(__('filament.user.last_login'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('organization.name')
                    ->label(__('filament.user.organization'))
                    ->sortable(),

                TextColumn::make('team.name')
                    ->label(__('filament.user.team')),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('organization_id')
                    ->label(__('filament.user.organization'))
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload()
                    ->hidden(Helper::checkPermission([
                        UserRole::ADMIN->value,
                        UserRole::ACCOUNTING->value,
                    ], Auth::user()->role)),

                SelectFilter::make('role')
                    ->label(__('filament.user.role'))
                    ->options(
                        \App\Common\Constants\User\UserRole::getOptions()
                    ),

                SelectFilter::make('position')
                    ->label(__('filament.user.position'))
                    ->options(
                        \App\Common\Constants\User\UserPosition::getOptions()
                    ),

                TernaryFilter::make('disable')
                    ->label(__('common.status.label'))
                    ->trueLabel(__('common.status.disabled'))
                    ->falseLabel(__('common.status.enabled'))
                    ->nullable(),

                TrashedFilter::make()
                    ->label(__('common.table.trashed')),
            ])
            ->recordActions([
                ActionGroup::make([
                    Impersonate::make()
                        ->visible(
                            fn($record) =>
                            Auth::user() && Helper::checkPermission([
                                UserRole::SUPER_ADMIN->value,
                                UserRole::ADMIN->value,
                                UserRole::ACCOUNTING->value,
                            ], Auth::user()->role) && Auth::user()->id !== $record->id
                        ),
                    Action::make('export_user')
                        ->label(__('common.action.export_excel'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->action(fn($record) => \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\UserDetailExport($record), 'user-' . $record->username . '.xlsx')),
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
                        ->visible(fn($record) => ! $record->trashed()),

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
                    Action::make('export_excel')
                        ->label(__('common.action.export_excel'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function () {
                            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\UsersExport, 'users.xlsx');
                        }),
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
            ]);
    }
}
