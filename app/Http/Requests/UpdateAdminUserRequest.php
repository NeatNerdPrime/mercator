<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends BaseFormRequest
{
    protected array $htmlFields = ['description'];

    public function authorize(): bool
    {
        return $this->authorizeEdit();
    }

    public function rules(): array
    {
        return [
            'perimeter_id' => ['nullable', 'integer', Rule::in(auth()->user()?->perimeterIds() ?? [])],
            'user_id' => [
                'min:3',
                'max:32',
                'required',
            ],
            'firstname' => [
                'max:64',
            ],
            'lastname' => [
                'max:64',
            ],
        ];
    }
}
