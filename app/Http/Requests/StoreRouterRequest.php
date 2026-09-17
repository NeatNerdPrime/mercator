<?php

namespace App\Http\Requests;

use App\Rules\IPList;
use Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class StoreRouterRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description', 'rules'];

    public function authorize()
    {
        abort_if(Gate::denies('router_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        $perimeterId = $this->input('perimeter_id') ?: auth()->user()?->activeOrDefaultPerimeterId();

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:32',
                'required',
                Rule::unique('routers')->where('perimeter_id', $perimeterId)->whereNull('deleted_at'),
            ],
            'physicalRouters.*' => [
                'integer',
            ],
            'physicalRouters' => [
                'array',
            ],
            'ip_addresses' => [
                'nullable',
                new IPList,
            ],
        ];
    }
}
