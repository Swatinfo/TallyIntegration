<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Http\Requests\Concerns\AcceptsFieldAliases;
use Modules\Tally\Rules\SafeXmlString;
use Modules\Tally\Services\Fields\TallyFieldRegistry;

class StoreGodownRequest extends FormRequest
{
    use AcceptsFieldAliases;

    protected string $tallyEntity = TallyFieldRegistry::GODOWN;

    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:255', new SafeXmlString],
            'PARENT' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'ADDRESS' => ['nullable', 'string', 'max:500', new SafeXmlString],
            'STORAGETYPE' => ['nullable', 'string', 'in:Not Applicable,External Godown,Our Godown'],
            'HASRELATEDPARTY' => ['nullable', 'string', 'in:Yes,No'],
        ];
    }
}
