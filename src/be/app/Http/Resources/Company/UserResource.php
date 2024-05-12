<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'fin' => $this->fin,
            'name' => $this->name,
            'surname' => $this->surname,
            'father' => $this->father,
            'dob' => $this->dob->format('d.m.Y'),
            'email' => $this->email,
            'status' => $this->status->description(),
            'created_at' => $this->created_at->format('d.m.Y H:i:s'),
            'updated_at' => $this->updated_at->format('d.m.Y H:i:s'),
        ];
    }
}
