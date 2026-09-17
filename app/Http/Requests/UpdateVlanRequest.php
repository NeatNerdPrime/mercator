<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateVlanRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules(): array
    {
        $perimeterId = $this->input('perimeter_id') ?: $this->route('vlan')?->perimeter_id;

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:64',
                'required',
                Rule::unique('vlans')->where('perimeter_id', $perimeterId)
                    ->ignore($this->route('vlan')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
