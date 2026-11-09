<?php

namespace App\Http\Requests\Company;

use App\Enums\UserStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StoreUserRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('fin') && is_string($this->fin)) {
            $this->merge(['fin' => strtoupper(trim($this->fin))]);
        }
    }

    public function rules(): array
    {
        return [
            'fin' => 'required|string|size:7|regex:/^[A-Z0-9]+$/|unique:company_users,fin',
            'name' => 'required|string|min:2|max:20|regex:/^[\pL\s]+$/u',
            'surname' => 'required|string|min:2|max:50|regex:/^[\pL\s]+$/u',
            'father' => 'required|string|min:2|max:20|regex:/^[\pL\s]+$/u',
            'dob' => 'required|date_format:Y-m-d',
            'email' => 'required|email|unique:company_users,email',
            'status' => ['required', Rule::in(Arr::map(UserStatus::cases(), static fn ($enum) => $enum->value))],
        ];
    }

    public function messages(): array
    {
        return [
            'fin.required' => 'Şirkət işçisinin FİN-i qeyd edilməlidir.',
            'fin.size' => 'Şirkət işçisinin FİN-i 7 simvoldan ibarət olmalıdır.',
            'fin.regex' => 'Şirkət işçisinin FİN-i yalnız böyük hərf (A-Z) və rəqəmlərdən (0-9) ibarət olmalıdır.',
            'fin.unique' => 'Bu FİN artıq mövcuddur.',
            'name.required' => 'Şirkət işçisinin adı qeyd edilməlidir.',
            'surname.required' => 'Şirkət işçisinin soyadı qeyd edilməlidir.',
            'father.required' => 'Şirkət işçisinin ata adı qeyd edilməlidir.',
            'dob.required' => 'Şirkət işçisinin doğum tarixi qeyd edilməlidir.',
            'dob.date_format' => 'Doğum tarixi Y-m-d formatında olmalıdır.',
            'email.required' => 'Email qeyd edilməlidir.',
            'email.unique' => 'Bu email artıq mövcuddur.',
            'status.required' => 'Status seçilməlidir.',
        ];
    }
}
