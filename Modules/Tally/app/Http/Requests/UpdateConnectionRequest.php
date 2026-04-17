<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Rules\SafeXmlString;

class UpdateConnectionRequest extends FormRequest
{
    public function rules(): array
    {
        $connectionId = $this->route('connection')?->id ?? $this->route('connection');

        return [
            'name' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'code' => ['nullable', 'string', 'max:20', 'alpha_num', 'unique:tally_connections,code,'.$connectionId],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'company_name' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'timeout' => ['nullable', 'integer', 'min:5', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
