<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Http\Requests\Concerns\AcceptsFieldAliases;
use Modules\Tally\Rules\SafeXmlString;
use Modules\Tally\Services\Fields\TallyFieldRegistry;

class StoreStockItemRequest extends FormRequest
{
    use AcceptsFieldAliases;

    protected string $tallyEntity = TallyFieldRegistry::STOCK_ITEM;

    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:255', new SafeXmlString],
            'PARENT' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'BASEUNITS' => ['nullable', 'string', 'max:50'],
            'OPENINGBALANCE' => ['nullable', 'string', 'max:100'],
            'OPENINGRATE' => ['nullable', 'string', 'max:100'],
            'OPENINGVALUE' => ['nullable', 'numeric'],
            'HASBATCHES' => ['nullable', 'string', 'in:Yes,No'],
        ];
    }
}
