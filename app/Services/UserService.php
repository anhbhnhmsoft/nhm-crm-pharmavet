<?php

namespace App\Services;

use App\Core\ServiceReturn;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Throwable;

class UserService
{
    protected UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

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
            if (!empty($filters['disable'])) {
                $query->where('disable', $filters['disable']);
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
                $query->where(function ($q) use ($keyword) {
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
            $result = $this->userRepository->query()->whereIn('id', $users)->update(['team_id' => $teamId]);
            if ($ableRemove) {
                $this->userRepository->query()->where('team_id', $teamId)
                    ->whereNotIn('id', $users)
                    ->update(['team_id' => null]);
            }
            DB::commit();
            return ServiceReturn::success($result);
        } catch (Throwable $thr) {
            DB::rollBack();
            Log::error($thr);
            return ServiceReturn::error($thr->getMessage());
        }
    }
}
