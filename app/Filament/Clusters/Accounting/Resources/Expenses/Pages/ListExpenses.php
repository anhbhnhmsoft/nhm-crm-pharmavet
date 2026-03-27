<?php

namespace App\Filament\Clusters\Accounting\Resources\Expenses\Pages;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Filament\Clusters\Accounting\Resources\Expenses\ExpenseResource;
use App\Filament\Clusters\Accounting\Widgets\ExpenseSummaryWidget;
use App\Services\ExpenseService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

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
                    \Filament\Forms\Components\FileUpload::make('payroll_file')
                        ->label('Bảng lương (PDF/Excel/Image)')
                        ->disk('public')
                        ->directory('expense_attachments/payroll')
                        ->required()
                        ->helperText('Bắt buộc tải lên bảng lương để audit'),
                ])
                ->action(function (array $data) {
                    $month = now()->parse($data['month'])->format('m/Y');

                    \App\Models\Expense::create([
                        'organization_id' => Auth::user()->organization_id,
                        'expense_date' => now()->parse($data['month'])->endOfMonth(),
                        'category' => ExpenseCategory::OPERATIONAL->value,
                        'description' => "Tổng lương tháng $month",
                        'unit_price' => $data['total_salary'],
                        'quantity' => 1,
                        'amount' => $data['total_salary'],
                        'attachments' => [$data['payroll_file']],
                        'created_by' => Auth::id(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Đã thêm chi phí lương tháng ' . $month)
                        ->send();
                }),
            Action::make('batch_expense')
                ->label('Đối soát Chi phí (Excel)')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('success')
                ->form([
                    FileUpload::make('file')
                        ->label('Tải lên file Excel chi phí')
                        ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'])
                        ->required()
                        ->disk('local')
                        ->directory('temp_imports'),
                    Placeholder::make('note')
                        ->content('File Excel cần có đầy đủ các cột: "Ngày phát sinh", "Danh mục", "Đơn giá", "Số lượng", "Mô tả", "Ghi chú".')
                ])
                ->action(function (array $data, ExpenseService $service) {
                    $disk = 'local';
                    $filePath = Storage::disk($disk)->path($data['file']);

                    try {
                        if (!Storage::disk($disk)->exists($data['file'])) {
                            throw new \Exception('Không tìm thấy file trên server.');
                        }

                        $rows = Excel::toArray(new class {}, $filePath);
                        $sheet = $rows[0] ?? [];

                        if (empty($sheet)) {
                            throw new \Exception('File Excel trống');
                        }

                        $header = array_shift($sheet);
                        $normalizedHeader = array_map(fn($h) => trim(mb_strtolower($h)), $header);

                        $requiredHeaders = [
                            'date' => ['ngày phát sinh', 'ngay phat sinh', 'ngày chi', 'date'],
                            'category' => ['danh mục', 'danh muc', 'nhóm chi phí', 'category'],
                            'unit_price' => ['đơn giá', 'don gia', 'unit price', 'giá'],
                            'quantity' => ['số lượng', 'so luong', 'quantity', 'sl'],
                            'description' => ['mô tả', 'mo ta', 'description', 'nội dung'],
                            'note' => ['ghi chú', 'ghi chu', 'note']
                        ];

                        $colMapping = [];
                        foreach ($requiredHeaders as $key => $aliases) {
                            foreach ($aliases as $alias) {
                                $idx = array_search($alias, $normalizedHeader);
                                if ($idx !== false) {
                                    $colMapping[$key] = $idx;
                                    break;
                                }
                            }
                        }

                        $missing = [];
                        if (!isset($colMapping['date']))
                            $missing[] = '"Ngày phát sinh"';
                        if (!isset($colMapping['category']))
                            $missing[] = '"Danh mục"';
                        if (!isset($colMapping['unit_price']))
                            $missing[] = '"Đơn giá"';
                        if (!isset($colMapping['quantity']))
                            $missing[] = '"Số lượng"';
                        if (!isset($colMapping['description']))
                            $missing[] = '"Mô tả"';

                        if (!empty($missing)) {
                            throw new \Exception('File thiếu các cột bắt buộc: ' . implode(', ', $missing));
                        }

                        $items = [];
                        foreach ($sheet as $row) {
                            $price = (float) str_replace([',', '.'], '', $row[$colMapping['unit_price']] ?? 0);
                            $qty = (int) ($row[$colMapping['quantity']] ?? 1);

                            $items[] = [
                                'expense_date' => $row[$colMapping['date']] ?? null,
                                'category' => $row[$colMapping['category']] ?? '',
                                'unit_price' => $price,
                                'quantity' => $qty,
                                'amount' => $price * $qty,
                                'description' => $row[$colMapping['description']] ?? '',
                                'note' => isset($colMapping['note']) ? ($row[$colMapping['note']] ?? '') : '',
                            ];
                        }

                        if (empty($items)) {
                            throw new \Exception('Không tìm thấy dữ liệu hợp lệ trong file.');
                        }

                        $result = $service->processBatchExpenses(
                            organizationId: Auth::user()->organization_id,
                            items: $items
                        );

                        if ($result->isError()) {
                            Notification::make()
                                ->danger()
                                ->title('Lỗi xử lý')
                                ->body($result->getMessage())
                                ->send();
                        } else {
                            $resData = $result->getData();
                            Notification::make()
                                ->success()
                                ->title('Thành công')
                                ->body($result->getMessage())
                                ->send();

                            $this->dispatch('$refresh');
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Lỗi xử lý file')
                            ->body($e->getMessage())
                            ->send();
                    } finally {
                        if (Storage::disk($disk)->exists($data['file'])) {
                            Storage::disk($disk)->delete($data['file']);
                        }
                    }
                }),
        ];
    }
}
