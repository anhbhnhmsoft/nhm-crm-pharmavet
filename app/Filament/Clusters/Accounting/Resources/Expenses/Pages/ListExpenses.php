<?php

namespace App\Filament\Clusters\Accounting\Resources\Expenses\Pages;

use App\Filament\Clusters\Accounting\Resources\Expenses\ExpenseResource;
use App\Filament\Clusters\Accounting\Widgets\ExpenseSummaryWidget;
use App\Services\ExpenseService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
            Action::make('add_monthly_salary')
                ->label(__('accounting.expense.salary_month_action'))
                ->icon('heroicon-o-currency-dollar')
                ->color('success')
                ->form([
                    DatePicker::make('month')
                        ->label(__('accounting.expense.salary_month_month'))
                        ->required()
                        ->extraInputAttributes(['required' => false])
                        ->default(now())
                        ->native(false)
                        ->displayFormat('m/Y')
                        ->validationMessages([
                            'required' => __('common.error.required'),
                        ]),
                    TextInput::make('total_salary')
                        ->label(__('accounting.expense.salary_month_total_salary'))
                        ->numeric()
                        ->required()
                        ->extraInputAttributes([
                            'type' => 'text',
                            'inputmode' => 'decimal',
                            'required' => false,
                            'min' => null,
                            'max' => null,
                            'step' => null,
                        ])
                        ->minValue(0)
                        ->prefix('₫')
                        ->helperText(__('accounting.expense.salary_month_total_salary_help'))
                        ->default(fn () => app(ExpenseService::class)->getDefaultMonthlySalary(Auth::user()->organization_id))
                        ->validationMessages([
                            'required' => __('common.error.required'),
                            'numeric' => __('common.error.numeric'),
                            'min' => __('common.error.min_value', ['min' => 0]),
                        ]),
                    FileUpload::make('payroll_file')
                        ->label(__('accounting.expense.salary_month_payroll_file'))
                        ->disk('public')
                        ->directory('expense_attachments/payroll')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'image/*',
                        ])
                        ->required()
                        ->extraInputAttributes(['required' => false])
                        ->validationMessages([
                            'required' => __('common.error.required'),
                        ])
                        ->helperText(__('accounting.expense.salary_month_payroll_file_help')),
                ])
                ->action(function (array $data, ExpenseService $service): void {
                    $result = $service->createMonthlySalaryExpense(
                        organizationId: Auth::user()->organization_id,
                        createdBy: Auth::id(),
                        month: (string) $data['month'],
                        totalSalary: (float) $data['total_salary'],
                        payrollFile: (string) $data['payroll_file'],
                    );

                    Notification::make()
                        ->{$result->isError() ? 'danger' : 'success'}()
                        ->title($result->isError() ? __('accounting.expense.create_failed') : $result->getMessage())
                        ->body($result->isError() ? $result->getMessage() : null)
                        ->send();

                    if ($result->isSuccess()) {
                        $this->dispatch('$refresh');
                    }
                }),
            Action::make('batch_expense')
                ->label(__('accounting.expense.batch_import.action'))
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('success')
                ->form([
                    FileUpload::make('file')
                        ->label(__('accounting.expense.batch_import.file'))
                        ->acceptedFileTypes([
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                        ])
                        ->required()
                        ->extraInputAttributes(['required' => false])
                        ->validationMessages([
                            'required' => __('common.error.required'),
                        ])
                        ->disk('local')
                        ->directory('temp_imports'),
                    Placeholder::make('note')
                        ->content(__('accounting.expense.batch_import.placeholder')),
                ])
                ->action(function (array $data, ExpenseService $service): void {
                    $result = $service->importBatchExpensesFromUploadedFile(
                        organizationId: Auth::user()->organization_id,
                        createdBy: Auth::id(),
                        disk: 'local',
                        path: (string) $data['file'],
                    );

                    Notification::make()
                        ->{$result->isError() ? 'danger' : 'success'}()
                        ->title(
                            $result->isError()
                                ? __('accounting.expense.batch_import.error_title')
                                : __('accounting.expense.batch_import.success_title')
                        )
                        ->body($result->getMessage())
                        ->send();

                    if ($result->isSuccess()) {
                        $this->dispatch('$refresh');
                    }
                }),
        ];
    }
}
