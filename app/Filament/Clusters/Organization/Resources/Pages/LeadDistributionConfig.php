<?php

namespace App\Filament\Clusters\Organization\Resources\Pages;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Organization\Support\BaseLeadDistributionConfigPage;
use App\Utils\Helper;
use Illuminate\Support\Facades\Auth;

class LeadDistributionConfig extends BaseLeadDistributionConfigPage
{
    public static function canAccess(): bool
    {
        return Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
        ], Auth::user()->role) && ! is_null(Auth::user()->organization_id);
    }
}
