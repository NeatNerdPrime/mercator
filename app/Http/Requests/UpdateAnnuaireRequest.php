<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateAnnuaireRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules(): array
    {
        $perimeterId = $this->input('perimeter_id') ?: $this->route('annuaire')?->perimeter_id;

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:32',
                'required',
                Rule::unique('annuaires')->where('perimeter_id', $perimeterId)
                    ->ignore($this->route('annuaire')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
            'application_id' => ['nullable', 'integer', 'exists:applications,id'],
        ];
    }
}
