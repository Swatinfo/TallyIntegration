<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Tally\Rules\SafeXmlString;
use Modules\Tally\Services\Vouchers\VoucherType;

class ReconcileVoucherRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'voucher_number' => ['required', 'string', 'max:100', new SafeXmlString],
            'voucher_date' => ['required', 'string', 'size:8'],              // YYYYMMDD
            'voucher_type' => ['required', new Enum(VoucherType::class)],
            'statement_date' => ['required', 'string', 'max:20'],            // DD-Mon-YYYY or similar
            'bank_ledger' => ['required', 'string', 'max:255', new SafeXmlString],
        ];
    }
}
