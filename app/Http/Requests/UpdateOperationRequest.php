<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateOperationRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules(): array
    {
        $perimeterId = $this->input('perimeter_id') ?: $this->route('operation')?->perimeter_id;

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:64',
                'required',
                Rule::unique('operations')->where('perimeter_id', $perimeterId)
                    ->ignore($this->route('operation')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
            'actors.*' => [
                'integer',
            ],
            'actors' => [
                'array',
            ],
            'tasks.*' => [
                'integer',
            ],
            'tasks' => [
                'array',
            ],
        ];
    }
}
