<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateApplicationFlowRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules(): array
    {
        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => ['min:3', 'max:64', 'required'],
            /*
            'application_source_id' =>
                ['required_without_all:service_source_id,module_source_id,database_source_id'],
            'service_source_id' =>
                ['required_without_all:application_source_id,module_source_id,database_source_id'],
            'module_source_id' =>
                ['required_without_all:application_source_id,service_source_id,database_source_id'],
            'database_source_id' =>
                ['required_without_all:application_source_id,service_source_id,module_source_id'],
            */

        ];
    }
}
