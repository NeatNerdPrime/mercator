<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateLogicalFlowRequest extends BaseFormRequest
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
            'name' => [
                'min:0',
                'max:64',
            ],
            'priority' => [
                'integer',
                'nullable',
            ],
        ];
    }
}
