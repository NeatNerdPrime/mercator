<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateActorRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules()
    {
        $perimeterId = $this->input('perimeter_id') ?: $this->route('actor')?->perimeter_id;

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:128',
                'required',
                Rule::unique('actors')->where('perimeter_id', $perimeterId)
                    ->ignore($this->route('actor')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
