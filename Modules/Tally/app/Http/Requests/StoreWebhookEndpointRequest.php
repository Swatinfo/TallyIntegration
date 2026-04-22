<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookEndpointRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tally_connection_id' => ['nullable', 'integer', 'exists:tally_connections,id'],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:*,master.created,master.updated,master.deleted,voucher.created,voucher.altered,voucher.cancelled,sync.completed,connection.health_changed'],
            'headers' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
