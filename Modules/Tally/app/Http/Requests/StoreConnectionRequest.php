<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Rules\SafeXmlString;

class StoreConnectionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new SafeXmlString],
            'code' => ['required', 'string', 'max:20', 'unique:tally_connections,code', 'alpha_num'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'company_name' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'timeout' => ['nullable', 'integer', 'min:5', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
