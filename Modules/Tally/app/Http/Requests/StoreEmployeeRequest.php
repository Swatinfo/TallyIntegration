<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Http\Requests\Concerns\AcceptsFieldAliases;
use Modules\Tally\Rules\SafeXmlString;
use Modules\Tally\Services\Fields\TallyFieldRegistry;

class StoreEmployeeRequest extends FormRequest
{
    use AcceptsFieldAliases;

    protected string $tallyEntity = TallyFieldRegistry::EMPLOYEE;

    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:255', new SafeXmlString],
            'PARENT' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'CATEGORY' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'EMPDISPLAYNAME' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'MAILINGNAME' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'CONTACTNUMBERS' => ['nullable', 'string', 'max:255'],
            'EMAIL' => ['nullable', 'email', 'max:255'],
            'DEACTIVATIONDATE' => ['nullable', 'string', 'max:32'],
            'EMPTIMERATE' => ['nullable', 'numeric'],
        ];
    }
}
