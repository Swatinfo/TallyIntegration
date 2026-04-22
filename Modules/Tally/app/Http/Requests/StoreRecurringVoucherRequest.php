<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Tally\Rules\SafeXmlString;
use Modules\Tally\Services\Vouchers\VoucherType;

class StoreRecurringVoucherRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new SafeXmlString],
            'voucher_type' => ['required', new Enum(VoucherType::class)],
            'frequency' => ['required', 'string', 'in:daily,weekly,monthly,quarterly,yearly'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:28', 'required_if:frequency,monthly,quarterly,yearly'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6', 'required_if:frequency,weekly'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'voucher_template' => ['required', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
