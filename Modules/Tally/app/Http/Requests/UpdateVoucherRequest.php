<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Tally\Services\Vouchers\VoucherType;

class UpdateVoucherRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(VoucherType::class)],
            'data' => ['required', 'array'],
        ];
    }
}
