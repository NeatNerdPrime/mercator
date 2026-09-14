<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateNetworkRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules(): array
    {
        $perimeterId = $this->input('perimeter_id') ?: $this->route('network')?->perimeter_id;

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:32',
                'required',
                Rule::unique('networks')->where('perimeter_id', $perimeterId)
                    ->ignore($this->route('network')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
            'security_need_c' => [
                'nullable',
                'integer',
                'min:0',
                'max:4',
            ],
            'security_need_i' => [
                'nullable',
                'integer',
                'min:0',
                'max:4',
            ],
            'security_need_a' => [
                'nullable',
                'integer',
                'min:0',
                'max:4',
            ],
            'security_need_t' => [
                'nullable',
                'integer',
                'min:0',
                'max:4',
            ],
            'subnetworks.*' => [
                'integer',
            ],
            'subnetworks' => [
                'array',
            ],
        ];
    }
}
