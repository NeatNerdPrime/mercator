<?php

namespace App\Http\Requests;

use App\Rules\IPList;
use Illuminate\Validation\Rule;

class UpdateRouterRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description', 'rules'];

    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules(): array
    {
        $perimeterId = $this->input('perimeter_id') ?: $this->route('router')?->perimeter_id;

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:32',
                'required',
                Rule::unique('routers')->where('perimeter_id', $perimeterId)
                    ->ignore($this->route('router')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
            'ip_addresses' => [
                'nullable',
                new IPList,
            ],
        ];
    }
}
