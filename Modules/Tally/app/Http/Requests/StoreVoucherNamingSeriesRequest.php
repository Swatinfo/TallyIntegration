<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Services\Vouchers\VoucherType;

class StoreVoucherNamingSeriesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'voucher_type' => ['required', 'string', 'max:64'],
            'series_name' => ['required', 'string', 'max:64'],
            'prefix' => ['nullable', 'string', 'max:32'],
            'suffix' => ['nullable', 'string', 'max:32'],
            'last_number' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $type = $this->input('voucher_type');
        if (is_string($type) && VoucherType::tryFrom($type) === null) {
            // Accept any free-text voucher type string — custom voucher types in Tally are common.
        }
    }
}
