<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dob = $this->dob;
        $createdAt = $this->created_at;
        $updatedAt = $this->updated_at;

        return [
            'fin' => $this->fin,
            'name' => $this->name,
            'surname' => $this->surname,
            'father' => $this->father,
            'dob' => $dob ? $dob->format('Y-m-d') : null,
            'dob_display' => $dob ? $dob->format('d.m.Y') : null,
            'email' => $this->email,
            'status' => $this->status?->value,
            'status_label' => $this->status?->description(),
            'created_at' => $createdAt?->format('d.m.Y H:i:s'),
            'updated_at' => $updatedAt?->format('d.m.Y H:i:s'),
        ];
    }
}
