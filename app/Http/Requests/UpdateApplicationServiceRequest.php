<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateApplicationServiceRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules(): array
    {
        $perimeterId = $this->input('perimeter_id') ?: $this->route('application_service')?->perimeter_id;

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:64',
                'required',
                Rule::unique('application_services')->where('perimeter_id', $perimeterId)
                    ->ignore($this->route('application_service')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
            'modules.*' => [
                'integer',
            ],
            'modules' => [
                'array',
            ],
        ];
    }
}
