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
        return [
            'name' => [
                'min:3',
                'max:32',
                'required',
                Rule::unique('annuaires')
                    ->ignore($this->route('annuaire')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
            'application_id' => ['nullable', 'integer', 'exists:applications,id'],
        ];
    }
}
