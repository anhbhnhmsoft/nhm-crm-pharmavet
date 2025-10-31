<?php

namespace App\Exports;

use App\Common\Constants\User\UserPosition;
use App\Common\Constants\User\UserRole;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UserDetailExport implements FromArray, WithHeadings
{
    public function __construct(protected $user) {}

    public function array(): array
    {
        $user = $this->user;
        $roleName = $user->role ? UserRole::getLabel((int) $user->role) : '';
        $positionName = $user->position ? UserPosition::getLabel((int) $user->position) : '';
        $status = $user->disable ? __('common.status.disabled') : __('common.status.enabled');
        return [[
            $user->id,
            $user->name,
            $user->username,
            $user->email,
            $user->phone,
            $roleName,
            $positionName,
            $user->organization?->name ?? '',
            $user->team?->name ?? '',
            $status,
            optional($user->last_login_at)->format('d/m/Y H:i') ?? '',
            optional($user->created_at)->format('d/m/Y H:i') ?? '',
            $user->createdBy?->name ?? '',
            optional($user->updated_at)->format('d/m/Y H:i') ?? '',
            $user->updatedBy?->name ?? '',
            $user->salary,
            $user->online_hours,
        ]];
    }
    public function headings(): array
    {
        return [
            'ID',
            __('common.table.name'),
            __('filament.user.username'),
            'Email',
            __('filament.user.phone'),
            __('filament.user.role'),
            __('filament.user.position'),
            __('filament.user.organization'),
            __('filament.user.team'),
            __('filament.user.status'),
            __('filament.user.last_login'),
            __('filament.user.created_at'),
            __('filament.user.created_by'),
            __('filament.user.updated_at'),
            __('filament.user.updated_by'),
            __('filament.user.salary'),
            __('filament.user.online_hours')
        ];
    }
}
