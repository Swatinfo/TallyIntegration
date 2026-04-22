<?php

namespace Modules\Tally\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tally\Http\Requests\Concerns\AcceptsFieldAliases;
use Modules\Tally\Rules\SafeXmlString;
use Modules\Tally\Services\Fields\TallyFieldRegistry;

class StoreAttendanceTypeRequest extends FormRequest
{
    use AcceptsFieldAliases;

    protected string $tallyEntity = TallyFieldRegistry::ATTENDANCE_TYPE;

    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:255', new SafeXmlString],
            'PARENT' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'ATTENDANCETYPE' => ['nullable', 'string', 'in:Attendance,Production,Leave-with-Pay,Leave-without-Pay'],
            'BASEUNITS' => ['nullable', 'string', 'max:50'],
        ];
    }
}
