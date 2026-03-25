<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Pages;

use App\Filament\Clusters\Accounting\Resources\Reconciliations\ReconciliationResource;
use App\Repositories\ShippingConfigRepository;
use App\Services\ReconciliationService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Core\Logging;
use Storage;

class ListReconciliations extends ListRecords
{
    protected static string $resource = ReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        $shippingConfigRepo = app(ShippingConfigRepository::class);
        $config = $shippingConfigRepo->query()
            ->where('organization_id', Auth::user()->organization_id)
            ->first();

        $hasConfig = $config && !empty($config->api_token) && !empty($config->default_store_id);

        return [
            Action::make('sync_ghn')
                ->label(__('accounting.reconciliation.sync_from_ghn'))
                ->icon('heroicon-o-arrow-path')
                ->disabled(!$hasConfig)
                ->tooltip(!$hasConfig ? __('accounting.reconciliation.config_not_found') : null)
                ->form([
                    DatePicker::make('from_date')
                        ->label(__('accounting.reconciliation.from_date'))
                        ->required()
                        ->default(now()->subDays(7)),
                    DatePicker::make('to_date')
                        ->label(__('accounting.reconciliation.to_date'))
                        ->required()
                        ->default(now())
                        ->after('from_date'),
                ])
                ->action(function (array $data) {
                    $service = app(ReconciliationService::class);
                    $result = $service->syncReconciliationFromGHN(
                        organizationId: Auth::user()->organization_id,
                        fromDate: $data['from_date'],
                        toDate: $data['to_date']
                    );

                    if ($result->isError()) {
                        Notification::make()
                            ->danger()
                            ->title(__('accounting.reconciliation.sync_failed'))
                            ->body($result->getMessage())
                            ->send();
                    } else {
                        $backfilledCount = $service->applyExchangeRateForDateRange(
                            organizationId: Auth::user()->organization_id,
                            fromDate: $data['from_date'],
                            toDate: $data['to_date']
                        );

                        Notification::make()
                            ->success()
                            ->title(__('accounting.reconciliation.synced', ['count' => ($result->getData()['created'] ?? 0) + ($result->getData()['updated'] ?? 0)]))
                            ->body(__('accounting.reconciliation.exchange_rate_auto_attached', ['count' => $backfilledCount]))
                            ->send();

                        $this->dispatch('$refresh');
                    }
                }),
            Action::make('batch_reconciliation')
                ->label(__('accounting.reconciliation.batch_reconciliation'))
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('success')
                ->form([
                    FileUpload::make('file')
                        ->label(__('accounting.reconciliation.upload_excel'))
                        ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'])
                        ->required()
                        ->disk('local')
                        ->directory('temp_imports'),
                    Placeholder::make('note')
                        ->content('File Excel cần có đầy đủ các cột nghiệp vụ để đối soát chính xác: "Mã vận đơn", "Mã đơn hàng", "Ngày đối soát", "Trạng thái đối soát", "Thành tiền/Tiền thu hộ", "Giá dịch vụ VC", "Tổng tiền", "Ghi chú kế toán", "Họ tên", "Số điện thoại", "Địa chỉ".')
                ])
                ->action(function (array $data) {
                    $disk = 'local';
                    $filePath = Storage::disk($disk)->path($data['file']);

                    try {
                        if (!Storage::disk($disk)->exists($data['file'])) {
                            throw new \Exception('Không tìm thấy file trên server sau khi upload.');
                        }

                        $rows = \Maatwebsite\Excel\Facades\Excel::toArray(new class {}, $filePath);
                        $sheet = $rows[0] ?? [];

                        if (empty($sheet)) {
                            throw new \Exception('File Excel trống');
                        }

                        $header = array_shift($sheet);
                        $normalizedHeader = array_map(fn($h) => trim(mb_strtolower($h)), $header);

                        // Validate columns - Bắt buộc đầy đủ các cột nghiệp vụ
                        $requiredHeaders = [
                            'ghn_code' => ['mã vận đơn', 'ma van don', 'tracking code', 'ghn order code'],
                            'order_code' => ['mã đơn hàng', 'ma don hang', 'order code'],
                            'reconciliation_date' => ['ngày đối soát', 'ngay doi soat', 'reconciliation date'],
                            'status' => ['trạng thái đối soát', 'trang thai doi soat', 'status'],
                            'cod' => ['tiền thu hộ', 'tien thu ho', 'cod amount', 'thu hộ', 'thành tiền'],
                            'shipping' => ['giá dịch vụ vc', 'gia dich vu vc', 'shipping fee', 'phí dịch vụ'],
                            'total' => ['tổng cộng', 'tong cong', 'total fee', 'tổng tiền'],
                            'note' => ['ghi chú kế toán', 'ghi chu ke toan', 'employee note'],
                            'name' => ['họ tên', 'ho ten', 'customer name', 'tên khách'],
                            'phone' => ['số điện thoại', 'so dien thoai', 'phone', 'sđt'],
                            'address' => ['địa chỉ', 'dia chi', 'address']
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
                        if (!isset($colMapping['ghn_code']))
                            $missing[] = '"Mã vận đơn"';
                        if (!isset($colMapping['order_code']))
                            $missing[] = '"Mã đơn hàng"';
                        if (!isset($colMapping['reconciliation_date']))
                            $missing[] = '"Ngày đối soát"';
                        if (!isset($colMapping['status']))
                            $missing[] = '"Trạng thái đối soát"';
                        if (!isset($colMapping['cod']))
                            $missing[] = '"Thành tiền/Tiền thu hộ"';
                        if (!isset($colMapping['shipping']))
                            $missing[] = '"Giá dịch vụ VC"';
                        if (!isset($colMapping['total']))
                            $missing[] = '"Tổng tiền"';
                        if (!isset($colMapping['note']))
                            $missing[] = '"Ghi chú kế toán"';
                        if (!isset($colMapping['name']))
                            $missing[] = '"Họ tên"';
                        if (!isset($colMapping['phone']))
                            $missing[] = '"Số điện thoại"';
                        if (!isset($colMapping['address']))
                            $missing[] = '"Địa chỉ"';

                        if (!empty($missing)) {
                            throw new \Exception('File thiếu các cột bắt buộc: ' . implode(', ', $missing));
                        }

                        $items = [];
                        foreach ($sheet as $row) {
                            $code = trim($row[$colMapping['ghn_code']] ?? '');
                            $statusText = trim(mb_strtolower($row[$colMapping['status']] ?? ''));

                            if (empty($code))
                                continue;

                            $targetStatus = null;
                            if (str_contains($statusText, 'đối soát') || str_contains($statusText, 'confirmed') || str_contains($statusText, 'xác nhận')) {
                                $targetStatus = ReconciliationStatus::CONFIRMED->value;
                            } elseif (str_contains($statusText, 'thanh toán') || str_contains($statusText, 'paid')) {
                                $targetStatus = ReconciliationStatus::PAID->value;
                            }

                            if ($targetStatus) {
                                $items[] = [
                                    'ghn_order_code' => $code,
                                    'target_status' => $targetStatus,
                                    'cod_amount' => (float) str_replace([',', '.'], '', $row[$colMapping['cod']] ?? 0),
                                    'shipping_fee' => (float) str_replace([',', '.'], '', $row[$colMapping['shipping']] ?? 0),
                                    'total_fee' => (float) str_replace([',', '.'], '', $row[$colMapping['total']] ?? 0),
                                    'reconciliation_date' => trim($row[$colMapping['reconciliation_date']] ?? ''),
                                    'ghn_employee_note' => trim($row[$colMapping['note']] ?? ''),
                                    'ghn_to_name' => trim($row[$colMapping['name']] ?? ''),
                                    'ghn_to_phone' => trim($row[$colMapping['phone']] ?? ''),
                                    'ghn_to_address' => trim($row[$colMapping['address']] ?? ''),
                                ];
                            }
                        }

                        if (empty($items)) {
                            throw new \Exception('Không tìm thấy dữ liệu hợp lệ nào trong file (Mã vận đơn trống hoặc trạng thái không hợp lệ)');
                        }

                        $service = app(ReconciliationService::class);
                        $result = $service->processBatchReconciliation(
                            organizationId: Auth::user()->organization_id,
                            items: $items
                        );

                        if ($result->isError()) {
                            Notification::make()
                                ->danger()
                                ->title(__('accounting.reconciliation.batch_failed'))
                                ->body($result->getMessage())
                                ->send();
                        } else {
                            $resData = $result->getData();
                            Notification::make()
                                ->success()
                                ->title(__('accounting.reconciliation.batch_success', ['count' => $resData['updated']]))
                                ->body("Thành công: {$resData['updated']} đơn. Bỏ qua: {$resData['skipped']} đơn (trùng/đã xử lý). Không tìm thấy: " . count($resData['not_found']) . " đơn.")
                                ->send();

                            if (!empty($resData['not_found'])) {
                                Logging::web('Batch reconciliation unmatched codes from Excel', [
                                    'not_found' => $resData['not_found']
                                ]);
                            }

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

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tất cả'),
            'pending' => Tab::make('Chờ xác nhận')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', ReconciliationStatus::PENDING->value)),
            'confirmed' => Tab::make('Đã xác nhận')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', ReconciliationStatus::CONFIRMED->value)),
            'cancelled' => Tab::make('Đã hủy')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', ReconciliationStatus::CANCELLED->value)),
            'paid' => Tab::make('Đã thanh toán')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', ReconciliationStatus::PAID->value)),
        ];
    }
}
