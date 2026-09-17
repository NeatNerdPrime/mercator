<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateForestAdRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules(): array
    {
        $perimeterId = $this->input('perimeter_id') ?: $this->route('forest_ad')?->perimeter_id;

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:32',
                'required',
                Rule::unique('forest_ads')->where('perimeter_id', $perimeterId)
                    ->ignore($this->route('forest_ad')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
            'domains.*' => [
                'integer',
            ],
            'domains' => [
                'array',
            ],
        ];
    }
}
