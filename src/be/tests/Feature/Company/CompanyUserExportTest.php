<?php

namespace Tests\Feature\Company;

use App\Models\Company\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyUserExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_returns_csv_with_header_and_all_rows(): void
    {
        $this->actingAsApiUser();
        User::factory()->create([
            'fin' => 'EX11111',
            'name' => 'Ali',
            'surname' => 'Aliyev',
            'father' => 'Vali',
            'dob' => '1990-01-15',
            'email' => 'ali.export@example.com',
            'status' => 'it_works',
        ]);
        User::factory()->count(2)->create();

        $response = $this->get('/api/company/users/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $lines = $this->csvLines($response->streamedContent());

        $this->assertSame(['fin', 'name', 'surname', 'father', 'dob', 'email', 'status', 'status_label'], $lines[0]);
        $this->assertCount(4, $lines); // header + 3 data rows

        $row = collect($lines)->first(fn ($line) => $line[0] === 'EX11111');
        $this->assertNotNull($row);
        $this->assertSame(['EX11111', 'Ali', 'Aliyev', 'Vali', '1990-01-15', 'ali.export@example.com', 'it_works'], array_slice($row, 0, 7));
    }

    public function test_export_respects_the_search_filter(): void
    {
        $this->actingAsApiUser();
        User::factory()->create(['fin' => 'AA11111', 'name' => 'Ali', 'surname' => 'Aliyev', 'email' => 'ali@example.com']);
        User::factory()->create(['fin' => 'BB22222', 'name' => 'Veli', 'surname' => 'Veliyev', 'email' => 'veli@example.com']);

        $response = $this->get('/api/company/users/export?search=Ali');

        $lines = $this->csvLines($response->streamedContent());

        $this->assertCount(2, $lines); // header + 1 matching data row
        $this->assertSame('AA11111', $lines[1][0]);
    }

    public function test_export_with_no_matches_returns_header_only_csv(): void
    {
        $this->actingAsApiUser();
        User::factory()->create();

        $response = $this->get('/api/company/users/export?search=no-such-person-zzz');

        $response->assertOk();
        $lines = $this->csvLines($response->streamedContent());

        $this->assertCount(1, $lines); // header row only
    }

    private function csvLines(string $csv): array
    {
        $lines = array_values(array_filter(explode("\n", $csv), fn ($line) => $line !== ''));

        return array_map('str_getcsv', $lines);
    }
}
