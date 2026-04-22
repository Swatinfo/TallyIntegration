<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Tally\Rules\SafeXmlString;
use Modules\Tally\Services\Vouchers\VoucherType;

class StoreDraftVoucherRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'voucher_type' => ['required', new Enum(VoucherType::class)],
            'voucher_data' => ['required', 'array'],
            'narration' => ['nullable', 'string', 'max:500', new SafeXmlString],
            'amount' => ['required', 'numeric', 'gte:0'],
        ];
    }
}
