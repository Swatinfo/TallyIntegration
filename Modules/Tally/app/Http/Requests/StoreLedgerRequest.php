<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Rules\SafeXmlString;

class StoreLedgerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:255', new SafeXmlString],
            'PARENT' => ['required', 'string', 'max:255', new SafeXmlString],
            'OPENINGBALANCE' => ['nullable', 'numeric'],
            'GSTREGISTRATIONTYPE' => ['nullable', 'string', 'max:50'],
            'PARTYGSTIN' => ['nullable', 'string', 'max:20'],
            'STATENAME' => ['nullable', 'string', 'max:100', new SafeXmlString],
            'EMAIL' => ['nullable', 'email', 'max:255'],
            'LEDGERPHONE' => ['nullable', 'string', 'max:50'],
            'LEDGERCONTACT' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'CREDITPERIOD' => ['nullable', 'string', 'max:50'],
            'CREDITLIMIT' => ['nullable', 'numeric'],
            'CURRENCYNAME' => ['nullable', 'string', 'max:10'],
        ];
    }
}
