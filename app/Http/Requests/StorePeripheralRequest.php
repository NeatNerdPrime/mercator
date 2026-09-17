<?php

namespace App\Http\Requests;

use App\Rules\IPList;
use Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class StorePeripheralRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        abort_if(Gate::denies('peripheral_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules(): array
    {
        $perimeterId = $this->input('perimeter_id') ?: auth()->user()?->activeOrDefaultPerimeterId();

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:32',
                'required',
                Rule::unique('peripherals')->where('perimeter_id', $perimeterId)->whereNull('deleted_at'),
            ],
            'iconFile' => ['nullable', 'file', 'mimes:png', 'max:65535'],
            'address_ip' => [
                'nullable',
                new IPList,
            ],
        ];
    }
}
