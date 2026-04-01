<?php

namespace App\Filament\Clusters\Marketing\Resources\Integrations\Pages;

use App\Common\Constants\Marketing\IntegrationType;
use App\Filament\Clusters\Marketing\Resources\Integrations\IntegrationResource;
use App\Services\Marketing\WebsiteLeadIngestService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditIntegration extends EditRecord
{
    protected static string $resource = IntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
