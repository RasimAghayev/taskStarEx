<?php

namespace App\Http\Requests\Company;

use App\Http\Requests\ApiFormRequest;

class ImportUsersRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            // Phase 9 hardening: this is a file-upload surface, so beyond
            // the pre-existing extension check (`mimes`) it also checks
            // the actually-sniffed content type (`mimetypes`, via PHP's
            // fileinfo -- catches a non-CSV file renamed with a .csv
            // extension) and caps the upload size so a single request
            // can't be used to exhaust disk/memory on this bulk-write
            // endpoint. 5 MB is generous for this entity's row size
            // (comfortably tens of thousands of rows) while still bounded.
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimes:csv,txt',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A CSV file is required.',
            'file.max' => 'The file must not be larger than 5 MB.',
            'file.mimes' => 'The file must be a CSV (or plain text) file.',
            'file.mimetypes' => 'The file content does not look like a CSV/plain-text file.',
        ];
    }
}
