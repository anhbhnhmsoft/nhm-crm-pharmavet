<?php

namespace App\Filament\Clusters\Accounting\Resources\Expenses\Pages;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Filament\Clusters\Accounting\Resources\Expenses\ExpenseResource;
use App\Filament\Clusters\Accounting\Widgets\ExpenseSummaryWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ExpenseSummaryWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['organization_id'] = Auth::user()->organization_id;
                    $data['created_by'] = Auth::id();
                    return $data;
                }),
            \Filament\Actions\Action::make('add_monthly_salary')
                ->label('Thêm lương tháng')
                ->icon('heroicon-o-currency-dollar')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('month')
                        ->label('Chọn tháng')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('m/Y'),
                    \Filament\Forms\Components\TextInput::make('total_salary')
                        ->label('Tổng lương dự kiến')
                        ->numeric()
                        ->prefix('₫')
                        ->helperText('Hệ thống tự động tính từ lương cơ bản của nhân viên hiện tại.')
                        ->default(fn() => \App\Models\User::where('organization_id', Auth::user()->organization_id)->where('disable', false)->sum('salary')),
                ])
                ->action(function (array $data) {
                    $month = now()->parse($data['month'])->format('m/Y');

                    \App\Models\Expense::create([
                        'organization_id' => Auth::user()->organization_id,
                        'expense_date' => now()->parse($data['month'])->endOfMonth(),
                        'category' => ExpenseCategory::SALES->value,
                        'description' => "Tổng lương tháng $month",
                        'amount' => $data['total_salary'],
                        'created_by' => Auth::id(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Đã thêm chi phí lương tháng ' . $month)
                        ->send();
                }),
        ];
    }
}
