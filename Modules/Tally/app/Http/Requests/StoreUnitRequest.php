<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Http\Requests\Concerns\AcceptsFieldAliases;
use Modules\Tally\Rules\SafeXmlString;
use Modules\Tally\Services\Fields\TallyFieldRegistry;

class StoreUnitRequest extends FormRequest
{
    use AcceptsFieldAliases;

    protected string $tallyEntity = TallyFieldRegistry::UNIT;

    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:50', new SafeXmlString],
            'ISSIMPLEUNIT' => ['nullable', 'string', 'in:Yes,No'],
            'BASEUNITS' => ['nullable', 'string', 'max:50', new SafeXmlString],
            'ADDITIONALUNITS' => ['nullable', 'string', 'max:50', new SafeXmlString],
            'CONVERSION' => ['nullable', 'numeric'],
        ];
    }
}
