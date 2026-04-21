<?php

namespace App\Http\Controllers;

use App\Common\Constants\GateKey;
use App\Common\Constants\User\UserRole;
use App\Services\Warehouse\OrderExportTicketPrintService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class OrderExportTicketPrintController extends Controller
{
    public function __invoke(Request $request, int $order, OrderExportTicketPrintService $printService): Response
    {
        abort_unless(
            Gate::allows(
                GateKey::HAS_ROLE->name,
                [
                    UserRole::SUPER_ADMIN,
                    UserRole::ADMIN,
                    UserRole::WAREHOUSE,
                ],
            ),
            403
        );

        $user = $request->user();
        $organizationId = $user?->isSuperAdmin() ? null : (int) $user?->organization_id;

        $result = $printService->generatePdf($order, $organizationId);

        abort_if($result->isError(), 404, $result->getMessage());

        $data = $result->getData();

        return response($data['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $data['filename'] . '"',
        ]);
    }
}
