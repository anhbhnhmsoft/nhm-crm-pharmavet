<?php

namespace App\Services;

use App\Core\ServiceReturn;
use App\Repositories\TeamRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Throwable;

class UserService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected TeamRepository $teamRepository,
    ) {}

    public function find($id)
    {
        try {
            $result = $this->userRepository->find($id);
            return ServiceReturn::success($result);
        } catch (Throwable $thr) {
            Log::error($thr->getMessage());
            return ServiceReturn::error($thr->getMessage());
        }
    }

    public function getListUser($filters)
    {
        try {

            $query = $this->userRepository->query();
            if (isset($filters['disable'])) {
                $query->where('disable', $filters['disable']);
            }

            if (array_key_exists('available_for_team', $filters)) {
                $teamId = $filters['available_for_team'];
                $query->where(function ($q) use ($teamId) {
                    $q->whereNull('team_id');
                    if ($teamId) {
                        $q->orWhere('team_id', $teamId);
                    }
                });
            }

            if (!empty($filters['organization_id'])) {
                $query->where('organization_id', $filters['organization_id']);
            }

            if (!empty($filters['position'])) {
                $query->where('position', $filters['position']);
            }

            if (!empty($filters['role'])) {
                $query->where('role', $filters['role']);
            }

            if (!empty($filters['created_by'])) {
                $query->where('created_by', $filters['created_by']);
            }

            if (!empty($filters['updated_by'])) {
                $query->where('updated_by', $filters['updated_by']);
            }

            if (!empty($filters['salary'])) {
                $query->where('salary', $filters['salary']);
            }

            if (!empty($filters['online_hours'])) {
                $query->where('online_hours', $filters['online_hours']);
            }

            if (!empty($filters['keyword'])) {
                $keyword = trim($filters['keyword']);
                $query->where(function (Builder $q) use ($keyword) {
                    $q->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('username', 'like', '%' . $keyword . '%')
                        ->orWhere('email', 'like', '%' . $keyword . '%');
                });
            }
            return ServiceReturn::success($query);
        } catch (Throwable $thr) {
            Log::error($thr->getMessage());
            return ServiceReturn::error();
        }
    }

    public function updateTeamFoMember(array $users, $teamId, $ableRemove)
    {
        DB::beginTransaction();
        try {
            $team = $this->teamRepository->find($teamId);
            if (!$team) {
                return ServiceReturn::error(__('common.error.not_found'));
            }

            if ($ableRemove) {
                $team->users()->sync($users);
            } else {
                $team->users()->syncWithoutDetaching($users);
            }

            DB::commit();
            return ServiceReturn::success(true);
        } catch (Throwable $thr) {
            DB::rollBack();
            Log::error($thr);
            return ServiceReturn::error($thr->getMessage());
        }
    }
}
