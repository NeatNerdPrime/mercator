<?php

namespace App\Http\Requests;

use App\Rules\Cidr;
use Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class StoreSubnetworkRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        abort_if(Gate::denies('subnetwork_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

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
                Rule::unique('subnetworks')->where('perimeter_id', $perimeterId)->whereNull('deleted_at'),
            ],
            'address' => [
                'nullable',
                new Cidr,
            ],
            'default_gateway' => [
                'nullable',
                'ip',
            ],
        ];
    }
}
