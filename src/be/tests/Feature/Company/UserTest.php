<?php


namespace Tests\Feature;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Company\User;

class UserTest extends TestCase
{
    public function testIndexReturnsDataInValidFormat() {

        $this->json('get','api/company/users')
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    "success",
                    'data' => [
                        '*' => [
                            'fin',
                            'name',
                            'surname',
                            'father',
                            'dob',
                            'email',
                            'status',
                            'dob',
                            'created_at',
                            'updated_at'
                        ]
                    ]
                ]
            );
    }
    public function testCompanyUserIsCreatedSuccessfully()
    {

        $userData=[
                "fin" => "123AZDL",
                "name" => "Gilberto",
                "surname" => "Hodkiewicz",
                "father" => "Lourdes",
                "dob" => "1987-12-06",
                "email" => "Oda521@yahoo.com",
                "status" => "it_works",
            ];
        $response = $this->postJson('/api/company/users', $userData);

        // Act
        $response
            ->assertStatus(201)
            ->assertJsonStructure([
                "success",
                'data' => [
                        'fin',
                        'name',
                        'surname',
                        'father',
                        'dob',
                        'email',
                        'status',
                        'dob',
                        'created_at',
                        'updated_at'
                ]
            ]);
        $this->assertDatabaseHas('company_users', $userData);
    }

    public function testCompanyUserIsPatchSuccessfully()
    {

        $userData=[
            "fin" => "123DZDL",
            "name" => "Gilberto",
            "surname" => "Hodkiewicz",
            "father" => "Lourdes",
            "dob" => "1987-12-06",
            "email" => "Oda521@yahoo.com",
            "status" => "it_works",
        ];
        $response = $this->patchJson('/api/company/users/123AZDL',$userData);

        // Act
        $response
            ->assertStatus(201)
            ->assertJsonStructure([
                "success",
                'data'
            ]);
        $this->assertDatabaseHas('company_users', $userData);
    }
//    public function testCompanyUserIsDeleteSuccessfully()
//    {
//        $response = $this->deleteJson('/api/company/users/123DZDL');
//        $response->assertStatus(204);
//    }
}
