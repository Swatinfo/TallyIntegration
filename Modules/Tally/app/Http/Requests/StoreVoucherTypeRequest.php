<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Rules\SafeXmlString;

class StoreVoucherTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:255', new SafeXmlString],
            // PARENT is the base voucher type (e.g. "Sales", "Receipt"); required on create.
            'PARENT' => ['required', 'string', 'max:50', new SafeXmlString],
            'ABBR' => ['nullable', 'string', 'max:20', new SafeXmlString],
            'NUMBERINGMETHOD' => ['nullable', 'string', 'in:Automatic,Automatic (Manual Override),Manual,Multi-user Auto'],
            'ISDEEMEDPOSITIVE' => ['nullable', 'string', 'in:Yes,No'],
            'AFFECTSSTOCK' => ['nullable', 'string', 'in:Yes,No'],
        ];
    }
}
