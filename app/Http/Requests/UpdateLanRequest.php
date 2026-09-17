<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateLanRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules()
    {
        $perimeterId = $this->input('perimeter_id') ?: $this->route('lan')?->perimeter_id;

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:32',
                'required',
                Rule::unique('lans')->where('perimeter_id', $perimeterId)
                    ->ignore($this->route('lan')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
