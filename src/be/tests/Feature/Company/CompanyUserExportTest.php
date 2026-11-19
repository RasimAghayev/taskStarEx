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

    /**
     * Phase 11: the export path is defense-in-depth, not validation
     * duplication -- App\Http\Controllers\Company\UserController::export()
     * calls App\Support\Csv\CsvSafety::sanitizeForExport() on every string
     * cell "regardless of field, since field-level input validation alone
     * doesn't guarantee a leading formula-trigger character can never
     * reach this point" (the controller's own comment). Phase 11 (this
     * phase) confirmed that is literally true: StoreUserRequest's
     * `name`/`surname`/`father`/`fin` regexes forbid '='/'+'/'-'/'@', but
     * nothing short of the export path itself stops a value that reached
     * the database by some other route (a direct Eloquent write, a
     * seeder, a future bulk-admin tool) from carrying one. This test
     * writes such a row directly via the model -- bypassing HTTP
     * validation on purpose, the exact scenario the comment describes --
     * and proves export still neutralizes it. Before this test, only
     * Unit\Support\Csv\CsvSafetyTest exercised the sanitizer function in
     * isolation; nothing proved the real export endpoint actually calls
     * it for a row that needed it.
     */
    public function test_export_sanitizes_a_formula_injection_payload_that_reached_the_database_by_another_route(): void
    {
        $this->actingAsApiUser();
        User::factory()->create([
            'fin' => 'CSVINJ1',
            'name' => '=cmd|calc',
            'surname' => '+Veliyev',
            'father' => '-Hasan',
            'email' => 'safe@example.com',
        ]);

        $response = $this->get('/api/company/users/export');
        $lines = $this->csvLines($response->streamedContent());

        $row = collect($lines)->first(fn ($line) => $line[0] === 'CSVINJ1');
        $this->assertNotNull($row);
        // Each previously-dangerous cell now carries CsvSafety's leading
        // single-quote prefix, so Excel/Sheets renders it as literal text
        // instead of evaluating it as a formula.
        $this->assertSame("'=cmd|calc", $row[1]);
        $this->assertSame("'+Veliyev", $row[2]);
        $this->assertSame("'-Hasan", $row[3]);
        // An ordinary value in the same row is passed through unchanged --
        // sanitization is targeted, not a blanket rewrite of every cell.
        $this->assertSame('safe@example.com', $row[5]);
    }

    /**
     * The one field that can carry a trigger character through real,
     * unmodified HTTP validation: StoreUserRequest's `email` rule is
     * Laravel's standard `email` validator, which (correctly, per RFC
     * 5321/5322) accepts a local-part starting with '+' -- e.g.
     * "+15551234567@example.com" is a legitimate address. Confirms the
     * full real path: POST /company/users (no CsvSafety check exists on
     * that write path, only on export/import) -> GET .../export actually
     * sanitizes it, not just that the unit-level function can.
     */
    public function test_export_sanitizes_a_legitimately_created_email_starting_with_a_trigger_character(): void
    {
        $this->actingAsApiUser();

        $created = $this->postJson('/api/company/users', [
            'fin' => 'CSVINJ2',
            'name' => 'Ali',
            'surname' => 'Aliyev',
            'father' => 'Vali',
            'dob' => '1990-01-01',
            'email' => '+15551234567@example.com',
            'status' => 'new_register',
        ]);
        $created->assertCreated();
        $this->assertDatabaseHas('company_users', ['fin' => 'CSVINJ2', 'email' => '+15551234567@example.com']);

        $response = $this->get('/api/company/users/export');
        $lines = $this->csvLines($response->streamedContent());

        $row = collect($lines)->first(fn ($line) => $line[0] === 'CSVINJ2');
        $this->assertNotNull($row);
        $this->assertSame("'+15551234567@example.com", $row[5]);
    }

    private function csvLines(string $csv): array
    {
        $lines = array_values(array_filter(explode("\n", $csv), fn ($line) => $line !== ''));

        return array_map('str_getcsv', $lines);
    }
}
