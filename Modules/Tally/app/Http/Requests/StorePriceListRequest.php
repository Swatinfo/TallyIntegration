<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Rules\SafeXmlString;

class StorePriceListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:255', new SafeXmlString],
            'USEFORGROUPS' => ['nullable', 'string', 'in:Yes,No'],
            // Optional per-item rate list: PRICELIST.LIST → [{ STOCKITEMNAME, RATE, ... }]
            'PRICELIST.LIST' => ['nullable', 'array'],
        ];
    }
}
