<?php

namespace App\Http\Controllers;

use App\Common\Constants\User\UserRole;
use App\Models\FundTransactionAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FundTransactionAttachmentController extends Controller
{
    public function show(FundTransactionAttachment $attachment): StreamedResponse|RedirectResponse
    {
        $attachment = $this->resolveAccessibleAttachment($attachment->id);

        if (!$attachment) {
            return redirect()->back();
        }

        $path = (string) $attachment->file_path;

        if ($path === '' || !Storage::disk('public')->exists($path)) {
            return redirect()->back();
        }

        return Storage::disk('public')->response(
            $path,
            $attachment->original_name ?: basename($path)
        );
    }

    public function download(FundTransactionAttachment $attachment): StreamedResponse|RedirectResponse
    {
        $attachment = $this->resolveAccessibleAttachment($attachment->id);

        if (!$attachment) {
            return redirect()->back();
        }

        $path = (string) $attachment->file_path;

        if ($path === '' || !Storage::disk('public')->exists($path)) {
            return redirect()->back();
        }

        return Storage::disk('public')->download(
            $path,
            $attachment->original_name ?: basename($path)
        );
    }

    protected function resolveAccessibleAttachment(int $attachmentId): ?FundTransactionAttachment
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        $query = FundTransactionAttachment::query()
            ->select('fund_transaction_attachments.*')
            ->join('fund_transactions', 'fund_transactions.id', '=', 'fund_transaction_attachments.fund_transaction_id')
            ->join('funds', 'funds.id', '=', 'fund_transactions.fund_id')
            ->where('fund_transaction_attachments.id', $attachmentId);

        if ($user->role !== UserRole::SUPER_ADMIN->value) {
            $query->where('funds.organization_id', $user->organization_id);
        }

        return $query->first();
    }
}
