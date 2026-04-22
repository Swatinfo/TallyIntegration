<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Http\Requests\Concerns\AcceptsFieldAliases;
use Modules\Tally\Rules\SafeXmlString;
use Modules\Tally\Services\Fields\TallyFieldRegistry;

class StoreCostCenterRequest extends FormRequest
{
    use AcceptsFieldAliases;

    protected string $tallyEntity = TallyFieldRegistry::COST_CENTRE;

    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:255', new SafeXmlString],
            'PARENT' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'CATEGORY' => ['nullable', 'string', 'max:255', new SafeXmlString],
        ];
    }
}
