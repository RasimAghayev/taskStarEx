<?php

namespace App\Http\Requests\Company;

use App\Enums\UserStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('fin') && is_string($this->fin)) {
            $this->merge(['fin' => strtoupper(trim($this->fin))]);
        }
    }

    public function rules(): array
    {
        $finParam = $this->route('user') ?? $this->route('fin');
        $currentFin = is_object($finParam) && property_exists($finParam, 'fin') ? $finParam->fin : $finParam;

        $required = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';
        if ($this->isMethod('put')) {
            $required = 'required';
        }

        return [
            'fin' => [$required, 'string', 'size:7', 'regex:/^[A-Z0-9]+$/', Rule::unique('company_users', 'fin')->ignore($currentFin, 'fin')],
            'name' => [$required, 'string', 'min:2', 'max:20', 'regex:/^[\pL\s]+$/u'],
            'surname' => [$required, 'string', 'min:2', 'max:50', 'regex:/^[\pL\s]+$/u'],
            'father' => [$required, 'string', 'min:2', 'max:20', 'regex:/^[\pL\s]+$/u'],
            'dob' => [$required, 'date_format:Y-m-d'],
            'email' => [$required, 'email', Rule::unique('company_users', 'email')->ignore($currentFin, 'fin')],
            'status' => ['sometimes', 'required', Rule::in(Arr::map(UserStatus::cases(), static fn ($enum) => $enum->value))],
        ];
    }

    public function messages(): array
    {
        return [
            'fin.size' => 'Şirkət işçisinin FİN-i 7 simvoldan ibarət olmalıdır.',
            'fin.regex' => 'Şirkət işçisinin FİN-i yalnız böyük hərf (A-Z) və rəqəmlərdən (0-9) ibarət olmalıdır.',
            'fin.unique' => 'Bu FİN artıq mövcuddur.',
            'dob.date_format' => 'Doğum tarixi Y-m-d formatında olmalıdır.',
            'email.unique' => 'Bu email artıq mövcuddur.',
        ];
    }
}
