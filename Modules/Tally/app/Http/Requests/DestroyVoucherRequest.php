<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Tally\Services\Vouchers\VoucherType;

class DestroyVoucherRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(VoucherType::class)],
            'date' => ['required', 'string', 'max:50'],
            'voucher_number' => ['required', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'in:delete,cancel'],
            'narration' => ['nullable', 'string', 'max:500'],
        ];
    }
}
