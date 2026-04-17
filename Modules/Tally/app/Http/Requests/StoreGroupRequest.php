<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Rules\SafeXmlString;

class StoreGroupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:255', new SafeXmlString],
            'PARENT' => ['required', 'string', 'max:255', new SafeXmlString],
        ];
    }
}
