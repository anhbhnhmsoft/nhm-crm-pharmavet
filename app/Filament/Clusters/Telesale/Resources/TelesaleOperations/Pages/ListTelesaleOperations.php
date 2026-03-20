<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Pages;

use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\TelesaleOperationResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ListTelesaleOperations extends ListRecords
{
    protected static string $resource = TelesaleOperationResource::class;

    public int $newLeadBadgeCount = 0;

    public function mount(): void
    {
        parent::mount();
        $this->newLeadBadgeCount = $this->getLeadBadgeCount();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_lead_notifications')
                ->label(__('telesale.messages.new_lead_badge'))
                ->badge($this->newLeadBadgeCount > 0 ? $this->newLeadBadgeCount : null)
                ->color($this->newLeadBadgeCount > 0 ? 'danger' : 'gray')
                ->action(function (): void {
                    $this->newLeadBadgeCount = 0;
                    $this->storeLeadBadgeCount(0);

                    Notification::make()
                        ->title(__('telesale.messages.clear_lead_badge'))
                        ->success()
                        ->send();
                }),
            CreateAction::make()
                ->label(__('telesale.actions.add_data')),
        ];
    }

    protected function getListeners(): array
    {
        $organizationId = (int) Auth::user()->organization_id;

        return [
            "echo:telesale.leads.{$organizationId},LeadCreated" => 'handleIncomingLead',
        ];
    }

    public function handleIncomingLead(): void
    {
        $this->newLeadBadgeCount++;
        $this->storeLeadBadgeCount($this->newLeadBadgeCount);

        Notification::make()
            ->title(__('telesale.messages.new_lead_detected'))
            ->success()
            ->send();
    }

    private function getLeadBadgeCount(): int
    {
        return (int) Cache::get($this->leadBadgeCacheKey(), 0);
    }

    private function storeLeadBadgeCount(int $count): void
    {
        Cache::put($this->leadBadgeCacheKey(), $count, now()->addDay());
    }

    private function leadBadgeCacheKey(): string
    {
        return 'telesale.new_lead.badge.' . Auth::id();
    }
}
