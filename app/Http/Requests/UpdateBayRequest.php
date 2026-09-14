<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateBayRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules(): array
    {
        $perimeterId = $this->input('perimeter_id') ?: $this->route('bay')?->perimeter_id;

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:32',
                'required',
                Rule::unique('bays')->where('perimeter_id', $perimeterId)
                    ->ignore($this->route('bay')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
