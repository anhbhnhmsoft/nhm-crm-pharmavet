<?php

namespace App\Filament\Clusters\Marketing\Resources\Integrations\Pages;

use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\User\UserRole;
use App\Core\ServiceReturn;
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
                            'email' => 'ping-test@crmquanly.nhmsoft.com',
                            'source_detail' => 'website_ping_test',
                        ],
                    ], $secret);

                    if ($result->isError()) {
                        Notification::make()
                            ->title(__('filament.integration.notifications.ping_failed'))
                            ->body($this->formatPingErrorMessage($result))
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

    protected function formatPingErrorMessage(ServiceReturn $result): string
    {
        $fieldMessages = $this->extractPingFieldMessages((array) data_get($result->getData(), 'errors', []));

        if ($fieldMessages !== []) {
            return $fieldMessages[0];
        }

        return $this->normalizePingApiMessage($result->getMessage());
    }

    protected function extractPingFieldMessages(array $errors): array
    {
        $messages = [];

        foreach ($errors as $fieldMessages) {
            $items = is_array($fieldMessages) ? $fieldMessages : [$fieldMessages];

            foreach ($items as $message) {
                $humanizedMessage = $this->humanizePingMessage($message);

                if ($humanizedMessage !== '' && !in_array($humanizedMessage, $messages, true)) {
                    $messages[] = $humanizedMessage;
                }
            }
        }

        return $messages;
    }

    protected function humanizePingMessage(mixed $message): string
    {
        $normalized = is_string($message) ? trim($message) : '';

        return match ($normalized) {
            'Email appears to be a placeholder.' => __('filament.integration.notifications.ping_email_placeholder'),
            'Name is required.' => __('filament.integration.notifications.ping_name_required'),
            'Phone number must contain 9-11 digits.',
            'Phone number is not valid.' => __('filament.integration.notifications.ping_phone_invalid'),
            default => $this->normalizePingApiMessage($normalized),
        };
    }

    protected function normalizePingApiMessage(?string $message): string
    {
        $normalized = trim((string) $message);

        return match (true) {
            $this->isTechnicalPingMessage($normalized) => __('filament.integration.notifications.ping_generic_error'),
            $normalized === 'Unauthorized' => __('filament.integration.notifications.ping_unauthorized'),
            $normalized === 'Website integration not found' => __('filament.integration.notifications.ping_not_found'),
            $normalized === 'Invalid payload' => __('filament.integration.notifications.ping_invalid_payload'),
            str_starts_with($normalized, 'Unsupported ') => __('filament.integration.notifications.ping_invalid_payload'),
            $normalized !== '' => $normalized,
            default => __('filament.integration.notifications.ping_generic_error'),
        };
    }

    protected function isTechnicalPingMessage(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        return str_starts_with($message, '<')
            || str_starts_with($message, '{')
            || str_starts_with($message, '[')
            || str_contains($message, 'SQLSTATE')
            || str_contains($message, 'Stack trace')
            || str_contains($message, '<!DOCTYPE html')
            || strlen($message) > 240;
    }
}
