<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Rules\SafeXmlString;

class StoreCurrencyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:10', new SafeXmlString],
            'MAILINGNAME' => ['nullable', 'string', 'max:100', new SafeXmlString],
            'FORMALNAME' => ['nullable', 'string', 'max:100', new SafeXmlString],
            'ISSUFFIX' => ['nullable', 'string', 'in:Yes,No'],
            'HASSYMBOLSPACE' => ['nullable', 'string', 'in:Yes,No'],
            'DECIMALPLACES' => ['nullable', 'integer', 'min:0', 'max:4'],
            'DECIMALSYMBOL' => ['nullable', 'string', 'max:5', new SafeXmlString],
        ];
    }
}
