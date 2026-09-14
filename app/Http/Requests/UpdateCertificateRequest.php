<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateCertificateRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules(): array
    {
        $perimeterId = $this->input('perimeter_id') ?: $this->route('certificate')?->perimeter_id;

        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'name' => [
                'min:3',
                'max:32',
                'required',
                Rule::unique('certificates')->where('perimeter_id', $perimeterId)
                    ->ignore($this->route('certificate')->id ?? $this->id)
                    ->whereNull('deleted_at'),
            ],
            'start_validity' => [
                'date',
                'nullable',
            ],
            'end_validity' => [
                'date',
                'nullable',
                // TODO : fixme
                // 'after:start_validity',
            ],
        ];
    }
}
