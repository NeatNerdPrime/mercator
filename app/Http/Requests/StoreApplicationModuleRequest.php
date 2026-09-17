<?php

namespace App\Http\Requests;

use Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class StoreApplicationModuleRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        abort_if(Gate::denies('application_module_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules(): array
    {
        $perimeterId = $this->input('perimeter_id') ?: auth()->user()?->activeOrDefaultPerimeterId();

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:64',
                'required',
                Rule::unique('application_modules')->where('perimeter_id', $perimeterId)->whereNull('deleted_at'),
            ],
        ];
    }
}
