<?php

namespace App\Filament\Clusters\Marketing\Resources\Integrations\Pages;

use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Marketing\Resources\Integrations\IntegrationResource;
use App\Services\Integrations\MetaBusinessService;
use App\Services\Marketing\WebsiteLeadIngestService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditIntegration extends EditRecord
{
    protected static string $resource = IntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveFacebookPages')
                ->label(__('filament.integration.actions.approve_pages'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn() => (int) ($this->record?->type ?? 0) === IntegrationType::FACEBOOK_ADS->value
                    && in_array((int) Auth::user()->role, [UserRole::SUPER_ADMIN->value, UserRole::ADMIN->value], true))
                ->action(function (MetaBusinessService $metaBusinessService): void {
                    $result = $metaBusinessService->approveConnections(Auth::user(), $this->record, []);

                    Notification::make()
                        ->title($result->getMessage())
                        ->status($result->isSuccess() ? 'success' : 'danger')
                        ->send();

                    if ($result->isSuccess()) {
                        $this->record->refresh();
                    }
                }),
            Action::make('rejectFacebookPages')
                ->label(__('filament.integration.actions.reject_pages'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('reason')
                        ->label(__('filament.integration.fields.status_reason'))
                        ->rows(3),
                ])
                ->visible(fn() => (int) ($this->record?->type ?? 0) === IntegrationType::FACEBOOK_ADS->value
                    && in_array((int) Auth::user()->role, [UserRole::SUPER_ADMIN->value, UserRole::ADMIN->value], true))
                ->action(function (array $data, MetaBusinessService $metaBusinessService): void {
                    $result = $metaBusinessService->rejectConnections(Auth::user(), $this->record, [], $data['reason'] ?? null);

                    Notification::make()
                        ->title($result->getMessage())
                        ->status($result->isSuccess() ? 'success' : 'danger')
                        ->send();

                    if ($result->isSuccess()) {
                        $this->record->refresh();
                    }
                }),
            Action::make('testWebsitePing')
                ->label(__('filament.integration.actions.test_ping'))
                ->icon('heroicon-o-bolt')
                ->color('info')
                ->visible(fn() => (int) ($this->record?->type ?? 0) === IntegrationType::WEBSITE->value)
                ->action(function (WebsiteLeadIngestService $websiteLeadIngestService): void {
                    $siteId = (string) data_get($this->record?->config, 'site_id', '');
                    $secret = (string) data_get($this->record?->config, 'webhook_secret', '');

                    $result = $websiteLeadIngestService->ping($siteId, [
                        'request_id' => 'ui_ping_' . now()->timestamp,
                        'lead' => [
                            'name' => 'Ping Test',
                            'phone' => '0900000000',
                            'email' => 'ping@example.com',
                            'source_detail' => 'website_ping_test',
                        ],
                    ], $secret);

                    if ($result->isError()) {
                        $errorData = $result->getData();
                        $errorDetail = data_get($errorData, 'errors');
                        $errorBody = is_array($errorDetail)
                            ? json_encode($errorDetail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            : $result->getMessage();

                        Notification::make()
                            ->title(__('filament.integration.notifications.ping_failed'))
                            ->body($errorBody)
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('filament.integration.notifications.ping_success'))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function mutateFormDataBeforeFill(array $data): array
    {
        $data['organization_id'] = Auth::user()->organization_id;
        $data['updated_by'] = Auth::user()->id;
        return parent::mutateFormDataBeforeFill($data);
    }
}
