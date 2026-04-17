<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Rules\SafeXmlString;

class UpdateLedgerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'PARENT' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'OPENINGBALANCE' => ['nullable', 'numeric'],
            'GSTREGISTRATIONTYPE' => ['nullable', 'string', 'max:50'],
            'PARTYGSTIN' => ['nullable', 'string', 'max:20'],
            'STATENAME' => ['nullable', 'string', 'max:100', new SafeXmlString],
            'EMAIL' => ['nullable', 'email', 'max:255'],
            'CREDITPERIOD' => ['nullable', 'string', 'max:50'],
            'CREDITLIMIT' => ['nullable', 'numeric'],
        ];
    }
}
