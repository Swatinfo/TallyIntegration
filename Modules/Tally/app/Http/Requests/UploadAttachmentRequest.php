<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAttachmentRequest extends FormRequest
{
    public function rules(): array
    {
        $maxKb = config('tally.integration.attachments.max_size_kb', 10240);
        $allowed = implode(',', config('tally.integration.attachments.allowed_mimes', ['pdf', 'png', 'jpg', 'jpeg']));

        return [
            'file' => ['required', 'file', "max:{$maxKb}", "mimes:{$allowed}"],
        ];
    }
}
