<?php

namespace App\Http\Requests\Company;

use App\Http\Requests\ApiFormRequest;

class ImportUsersRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A CSV file is required.',
            'file.mimes' => 'The file must be a CSV (or plain text) file.',
        ];
    }
}
