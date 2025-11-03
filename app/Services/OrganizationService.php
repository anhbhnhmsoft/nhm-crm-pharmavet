<?php

namespace App\Services;

use App\Core\ServiceReturn;
use App\Repositories\OrganizationRepository;
use Throwable;

class OrganizationService
{

    protected OrganizationRepository $organizationRepository;

    public function __construct(OrganizationRepository $organizationRepository)
    {
        $this->organizationRepository = $organizationRepository;
    }
    public function checkScalability($id)
    {
        try {

            $record = $this->organizationRepository->find($id);
            if (!$record) {
                return ServiceReturn::error(__('organization.error.not_found'));
            }
            if ($record->users->count() >= $record->maximum_employees) {
                return ServiceReturn::success(
                    [
                        'canDevelop' => false
                    ]
                );
            } else {
                return ServiceReturn::success(
                    [
                        'canDevelop' => true
                    ]
                );
            }
        } catch (Throwable $thr) {
            return ServiceReturn::error($thr->getMessage(), $thr);
        }
    }

    public function getListOrganization($filters)
    {
        try {

            $query = $this->organizationRepository->query();

            if (!empty($filters['product_field'])) {
                $query->where('product_field', $filters['product_field']);
            }

            if (!empty($filters['maximum_employees'])) {
                $query->where('maximum_employees', $filters['maximum_employees']);
            }

            if (!empty($filters['disable'])) {
                $query->where('disable', $filters['disable']);
            }


            if (!empty($filters['keyword'])) {
                $keyword = trim($filters['keyword']);
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('description', 'like', '%' . $keyword . '%')
                        ->orWhere('code', 'like', '%' . $keyword . '%')
                        ->orWhere('phone', 'like', '%' . $keyword . '%')
                        ->orWhere('address', 'like', '%' . $keyword . '%');
                });
            }
            return ServiceReturn::success($query);
        } catch (Throwable $thr) {
            return ServiceReturn::error();
        }
    }
}
