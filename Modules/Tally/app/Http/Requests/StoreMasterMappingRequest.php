<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterMappingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'max:32', 'in:ledger,group,stock_item,stock_group,cost_centre,godown,voucher_type,currency,unit,stock_category,price_level'],
            'tally_name' => ['required', 'string', 'max:255'],
            'erp_name' => ['required', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
