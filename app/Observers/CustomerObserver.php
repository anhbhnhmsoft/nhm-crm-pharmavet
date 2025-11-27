<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\CustomerStatusLog;
use Illuminate\Support\Facades\Auth;

class CustomerObserver
{
    /**
     * Handle the Customer "updating" event.
     */
    public function updating(Customer $customer): void
    {
        // Tự động log thay đổi trạng thái
        if ($customer->isDirty('status')) {
            $customer->statusLogs()->create([
                'from_status' => $customer->getOriginal('status'),
                'to_status' => $customer->status,
                'user_id' => Auth::id(),
            ]);
        }
    }

    /**
     * Handle the Customer "created" event.
     */
    public function created(Customer $customer): void
    {
        // Log trạng thái ban đầu
        if ($customer->status) {
            $customer->statusLogs()->create([
                'from_status' => null,
                'to_status' => $customer->status,
                'user_id' => Auth::id() ?? $customer->assigned_staff_id,
            ]);
        }
    }
}
