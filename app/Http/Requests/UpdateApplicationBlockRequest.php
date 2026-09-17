<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateApplicationBlockRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules(): array
    {
        $perimeterId = $this->input('perimeter_id') ?: $this->route('application_block')?->perimeter_id;

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:64',
                'required',
                Rule::unique('application_blocks')->where('perimeter_id', $perimeterId)
                    ->ignore($this->route('application_block')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
