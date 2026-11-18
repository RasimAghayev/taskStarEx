<?php

namespace Tests\Feature\Company;

use App\Models\Company\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CompanyUserImportTest extends TestCase
{
    use RefreshDatabase;

    private function csvFile(string $contents, string $name = 'import.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csvimport');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    public function test_all_valid_rows_are_imported(): void
    {
        $csv = "fin,name,surname,father,dob,email,status\n".
            "IM11111,Ali,Aliyev,Vali,1990-01-01,ali.import@example.com,new_register\n".
            "IM22222,Veli,Veliyev,Hasan,1991-02-02,veli.import@example.com,it_works\n".
            "IM33333,Sabir,Sabirov,Qabil,1992-03-03,sabir.import@example.com,vacation\n";

        $response = $this->post('/api/company/users/import', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', 3)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('company_users', ['fin' => 'IM11111', 'email' => 'ali.import@example.com']);
        $this->assertDatabaseHas('company_users', ['fin' => 'IM22222', 'email' => 'veli.import@example.com']);
        $this->assertDatabaseHas('company_users', ['fin' => 'IM33333', 'email' => 'sabir.import@example.com']);
    }

    public function test_mixed_file_reports_per_row_success_and_failure(): void
    {
        User::factory()->create(['fin' => 'EXIST11', 'email' => 'existing@example.com']);

        $csv = "fin,name,surname,father,dob,email,status\n".
            "MX11111,Ali,Aliyev,Vali,1990-01-01,mx1@example.com,new_register\n".     // valid
            "MX22222,Veli,Veliyev,Hasan,1991-02-02,mx2@example.com,not_a_status\n".  // invalid status
            "EXIST11,Dup,User,Dad,1990-01-01,dup@example.com,it_works\n".            // duplicate fin vs DB
            "MX44444,Sabir,Sabirov,Qabil,1992-03-03,mx4@example.com,vacation\n";      // valid

        $response = $this->post('/api/company/users/import', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.failed', 2);

        $this->assertDatabaseHas('company_users', ['fin' => 'MX11111']);
        $this->assertDatabaseHas('company_users', ['fin' => 'MX44444']);
        $this->assertDatabaseMissing('company_users', ['fin' => 'MX22222']);
        $this->assertDatabaseMissing('company_users', ['email' => 'dup@example.com']);

        $errors = $response->json('data.errors');
        $this->assertSame([3, 4], array_column($errors, 'row'));
    }

    public function test_in_file_duplicate_fin_is_reported_without_a_server_error(): void
    {
        $csv = "fin,name,surname,father,dob,email,status\n".
            "DF11111,Ali,Aliyev,Vali,1990-01-01,df1@example.com,new_register\n".
            "DF11111,Second,Copy,Dad,1991-02-02,df2@example.com,it_works\n";

        $response = $this->post('/api/company/users/import', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.failed', 1);

        $this->assertDatabaseHas('company_users', ['fin' => 'DF11111', 'email' => 'df1@example.com']);

        $errors = $response->json('data.errors');
        $this->assertStringContainsString('Duplicate fin', $errors[0]['messages'][0]);
    }

    public function test_malformed_header_is_rejected_before_any_row_is_processed(): void
    {
        $csv = "id,first_name,last_name\n1,Ali,Aliyev\n";

        $response = $this->post('/api/company/users/import', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('company_users', 0);
    }

    public function test_empty_file_is_rejected(): void
    {
        $response = $this->post('/api/company/users/import', [
            'file' => $this->csvFile(''),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('company_users', 0);
    }
}
