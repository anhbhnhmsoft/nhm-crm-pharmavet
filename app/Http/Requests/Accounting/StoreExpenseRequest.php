<?php

namespace App\Http\Requests\Accounting;

use App\Common\Constants\Accounting\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'integer', 'in:' . implode(',', array_column(ExpenseCategory::cases(), 'value'))],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'reconciliation_id' => ['nullable', 'integer', 'exists:reconciliations,id'],
            'note' => ['nullable', 'string'],
        ];
    }
}

