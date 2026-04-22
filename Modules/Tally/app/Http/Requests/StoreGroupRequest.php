<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Http\Requests\Concerns\AcceptsFieldAliases;
use Modules\Tally\Rules\SafeXmlString;
use Modules\Tally\Services\Fields\TallyFieldRegistry;

class StoreGroupRequest extends FormRequest
{
    use AcceptsFieldAliases;

    protected string $tallyEntity = TallyFieldRegistry::GROUP;

    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:255', new SafeXmlString],
            'PARENT' => ['required', 'string', 'max:255', new SafeXmlString],
        ];
    }
}
