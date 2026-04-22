<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportBankStatementRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Either upload a file OR send csv text in-body.
            'statement_file' => ['sometimes', 'file', 'mimes:csv,txt', 'max:10240'],
            'csv' => ['sometimes', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->hasFile('statement_file') && ! $this->filled('csv')) {
                $v->errors()->add('statement_file', 'Either statement_file (upload) or csv (inline string) is required.');
            }
        });
    }
}
