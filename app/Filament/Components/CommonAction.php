<?php

namespace App\Filament\Components;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class CommonAction
{
    /**
     * Tạo action quay lại
     * @param $resource
     * @return Action
     */
    public static function backAction($resource): Action
    {
        return Action::make('back')
            ->label("Quay lại")
            ->color('gray')
            ->url(fn() => $resource::getUrl('index'))
            ->icon(Heroicon::ChevronLeft);
    }

}
