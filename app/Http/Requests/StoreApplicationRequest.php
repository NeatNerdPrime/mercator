<?php

namespace App\Http\Requests;

use App\Rules\UrlList;
use Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class StoreApplicationRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        abort_if(Gate::denies('application_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

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
                Rule::unique('applications')->where('perimeter_id', $perimeterId)->whereNull('deleted_at'),
            ],
            'iconFile' => ['nullable', 'file', 'mimes:png', 'max:65535'],
            'entities.*' => [
                'integer',
            ],
            'entities' => [
                'array',
            ],
            'security_need' => [
                'nullable',
                'integer',
            ],
            'processes.*' => [
                'integer',
            ],
            'processes' => [
                'array',
            ],
            'services.*' => [
                'integer',
            ],
            'services' => [
                'array',
            ],
            'databases.*' => [
                'integer',
            ],
            'databases' => [
                'array',
            ],
            'logical_servers.*' => [
                'integer',
            ],
            'logical_servers' => [
                'array',
            ],
            'install_date' => [
                'date',
                'nullable',
            ],
            'prod_date' => [
                'date',
                'nullable',
            ],
            'update_date' => [
                'date',
                'nullable',
                // TODO : fixme
                // 'after:install_date',
            ],
            'urls' => [
                'nullable',
                new UrlList,
	    ],
	    'documentation' => [
                'nullable',
                new UrlList,
            ],
        ];
    }
}
