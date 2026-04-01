<?php

namespace App\Http\Controllers;

use App\Repositories\MarketingSpendAttachmentRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketingSpendAttachmentController extends Controller
{
    public function __construct(
        protected MarketingSpendAttachmentRepository $marketingSpendAttachmentRepository,
    ) {
    }

    public function download(int $attachmentId): StreamedResponse|RedirectResponse
    {
        $attachment = $this->marketingSpendAttachmentRepository->query()
            ->join('marketing_spends as ms', 'ms.id', '=', 'marketing_spend_attachments.marketing_spend_id')
            ->where('marketing_spend_attachments.id', $attachmentId)
            ->where('ms.organization_id', Auth::user()->organization_id)
            ->select('marketing_spend_attachments.file_path')
            ->first();

        if (!$attachment) {
            return redirect()->back();
        }

        $path = (string) $attachment->file_path;
        if ($path === '' || !Storage::disk('local')->exists($path)) {
            return redirect()->back();
        }

        return Storage::disk('local')->download($path);
    }
}
