<?php

namespace App\Http\Requests\Company;

use App\Enums\UserStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StoreUserRequest extends ApiFormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fin' => 'required|min:7|max:7|regex:/^[A-Z0-9\s]+$/',
            'name' => 'required|min:2|max:20|regex:/^[a-zA-Z\s]+$/',
            'surname' => 'required|min:2|max:50|regex:/^[a-zA-Z\s]+$/',
            'father' => 'required|min:2|max:20|regex:/^[a-zA-Z\s]+$/',
            'dob' => 'required|date_format:Y-m-d',
            'email' => 'required|email|unique:company_users,email',
            'status' => ['required', Rule::in(Arr::map(UserStatus::cases(), static fn($enum) => $enum->value))],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'fin.required' => 'Şirkət işçisinin FİN-i qeyd edərdiniz.',
            'fin.min' => 'Şirkət işçisinin FİN-i minimum 7 simvol qeyd edərdiniz.',
            'fin.max' => 'Şirkət işçisinin FİN-i maxmimum 7 simvol qeyd edərdiniz.',
            'fin.regex' => 'Şirkət işçisinin FİN-i, ancaq Böyük (A-Z) hərif və rəqəmlərdən (0-9) simvolları qeyd edərdiniz.',
            'name.required' => 'Şirkət işçisinin adını qeyd edərdiniz.',
            'name.min' => 'Şirkət işçisinin adı minimum 2 simvol qeyd edərdiniz.',
            'name.max' => 'Şirkət işçisinin adı maxmimum 20 simvol qeyd edərdiniz.',
            'name.regex' => 'Şirkət işçisinin adı, ancaq Böyük və kiçik (A-Z,A-z) hərif simvolları qeyd edərdiniz.',
            'surname.required' => 'Şirkət işçisinin soyadını qeyd edərdiniz.',
            'surname.min' => 'Şirkət işçisinin soyadı minimum 2 simvol qeyd edərdiniz.',
            'surname.max' => 'Şirkət işçisinin soyadı maxmimum 50 simvol qeyd edərdiniz.',
            'surname.regex' => 'Şirkət işçisinin soyadı, ancaq Böyük və kiçik (A-Z,A-z) hərif simvolları qeyd edərdiniz.',
            'father.required' => 'Şirkət işçisinin Ata adını qeyd edərdiniz.',
            'father.min' => 'Şirkət işçisinin Ata adı minimum 2 simvol qeyd edərdiniz.',
            'father.max' => 'Şirkət işçisinin Ata adı maxmimum 50 simvol qeyd edərdiniz.',
            'father.regex' => 'Şirkət işçisinin Ata adı, ancaq Böyük və kiçik (A-Z,A-z) hərif simvolları qeyd edərdiniz.',
            'dob.required' => 'Şirkət işçisinin doğum günü qeyd edərdiniz.',
            'dob.date_format' => 'Şirkət işçisinin doğum günü :attribute bu uyğun deyil :format formata.'
        ];
    }
}
