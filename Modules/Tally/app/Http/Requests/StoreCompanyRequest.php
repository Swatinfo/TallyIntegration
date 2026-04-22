<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tally_organization_id' => ['required', 'integer', 'exists:tally_organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'alpha_num'],
            'country' => ['nullable', 'string', 'size:2'],
            'base_currency' => ['nullable', 'string', 'max:10'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
