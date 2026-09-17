<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateWanRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules()
    {
        $perimeterId = $this->input('perimeter_id') ?: $this->route('wan')?->perimeter_id;

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:32',
                Rule::unique('wans')->where('perimeter_id', $perimeterId)
                    ->ignore($this->route('wan')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
            'mans' => [
                'array',
            ],
            'mans.*' => [
                'integer', 'exists:mans,id',
            ],
            'lans' => [
                'array',
            ],
            'lans.*' => [
                'integer', 'exists:lans,id',
            ],
        ];
    }
}
