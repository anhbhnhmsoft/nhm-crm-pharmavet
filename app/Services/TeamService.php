<?php

namespace App\Services;

use App\Core\ServiceReturn;
use App\Repositories\TeamRepository;
use Throwable;

class TeamService
{
    protected TeamRepository $teamRepository;

    public function __construct(TeamRepository $teamRepository)
    {
        $this->teamRepository = $teamRepository;
    }

    public function getListTeam($filters)
    {
        try {

            $query = $this->teamRepository->query();
            if (array_key_exists('status', $filters)) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['organization_id'])) {
                $query->where('organization_id', $filters['organization_id']);
            }

            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            if (!empty($filters['created_by'])) {
                $query->where('created_by', $filters['created_by']);
            }

            if (!empty($filters['updated_by'])) {
                $query->where('updated_by', $filters['updated_by']);
            }


            if (!empty($filters['keyword'])) {
                $keyword = trim($filters['keyword']);
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('description', 'like', '%' . $keyword . '%')
                        ->orWhere('code', 'like', '%' . $keyword . '%');
                });
            }
            return ServiceReturn::success($query);
        } catch (Throwable $thr) {
            return ServiceReturn::error();
        }
    }
}
