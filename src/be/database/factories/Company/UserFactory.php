<?php

namespace Database\Factories\Company;

use App\Enums\UserStatus;
use App\Models\Company\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $fin = strtoupper($this->faker->bothify('??#####'));

        return [
            'fin' => $fin,
            'name' => $this->faker->firstName(),
            'surname' => $this->faker->lastName(),
            'father' => $this->faker->firstName('male'),
            'dob' => $this->faker->date('Y-m-d', '-20 years'),
            'email' => $this->faker->unique()->safeEmail(),
            'status' => $this->faker->randomElement(UserStatus::values()),
        ];
    }
}
