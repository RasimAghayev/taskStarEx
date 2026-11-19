<?php

namespace Tests\Feature\Company;

use App\Models\Company\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyUserApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'fin' => 'AB12345',
            'name' => 'Rasim',
            'surname' => 'Aghayev',
            'father' => 'Ilham',
            'dob' => '1990-05-10',
            'email' => 'rasim@example.com',
            'status' => 'new_register',
        ], $overrides);
    }

    public function test_can_list_company_users(): void
    {
        $this->actingAsApiUser();
        User::factory()->count(2)->create();

        $response = $this->getJson('/api/company/users');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_create_company_user(): void
    {
        $this->actingAsApiUser();

        $response = $this->postJson('/api/company/users', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.fin', 'AB12345');

        $this->assertDatabaseHas('company_users', ['fin' => 'AB12345', 'email' => 'rasim@example.com']);
    }

    public function test_validation_fails_for_invalid_payload(): void
    {
        $this->actingAsApiUser();

        $response = $this->postJson('/api/company/users', $this->payload([
            'fin' => 'bad',
            'email' => 'not-an-email',
            'dob' => '10.05.1990',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['fin', 'email', 'dob']);
    }

    public function test_can_show_company_user(): void
    {
        $this->actingAsApiUser();
        $user = User::factory()->create(['fin' => 'ZX98765']);

        $response = $this->getJson('/api/company/users/ZX98765');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.fin', 'ZX98765');
    }

    public function test_can_update_company_user(): void
    {
        $this->actingAsApiUser();
        $user = User::factory()->create(['fin' => 'UP12345', 'email' => 'old@example.com']);

        $response = $this->putJson('/api/company/users/UP12345', $this->payload([
            'fin' => 'UP12345',
            'name' => 'Updated',
            'email' => 'new@example.com',
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated');

        $this->assertDatabaseHas('company_users', ['fin' => 'UP12345', 'name' => 'Updated', 'email' => 'new@example.com']);
    }

    public function test_can_search_and_paginate_company_users(): void
    {
        $this->actingAsApiUser();
        User::factory()->create(['fin' => 'AA11111', 'name' => 'Ali', 'surname' => 'Aliyev', 'email' => 'ali@example.com']);
        User::factory()->create(['fin' => 'BB22222', 'name' => 'Veli', 'surname' => 'Veliyev', 'email' => 'veli@example.com']);

        $response = $this->getJson('/api/company/users?search=Ali&per_page=1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.fin', 'AA11111');
    }

    public function test_can_delete_company_user(): void
    {
        $this->actingAsApiUser();
        User::factory()->create(['fin' => 'DL12345']);

        $response = $this->deleteJson('/api/company/users/DL12345');

        $response->assertNoContent();
        $this->assertDatabaseMissing('company_users', ['fin' => 'DL12345']);
    }
}
