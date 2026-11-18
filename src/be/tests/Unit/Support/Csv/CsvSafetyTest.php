<?php

namespace Tests\Unit\Support\Csv;

use App\Support\Csv\CsvSafety;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvSafetyTest extends TestCase
{
    // PHPUnit 13 (pinned by this repo) dropped PHPDoc-based test metadata
    // entirely -- a bare "@dataProvider" docblock annotation is now just a
    // comment, silently ignored, which is why this previously ran with 0
    // arguments. The #[DataProvider(...)] attribute below is the only
    // supported mechanism in this PHPUnit version.
    #[DataProvider('triggerCharacterProvider')]
    public function test_isFormulaInjection_detects_every_trigger_character(string $trigger): void
    {
        $this->assertTrue(CsvSafety::isFormulaInjection($trigger.'SUM(A1:A9)'));
    }

    public static function triggerCharacterProvider(): array
    {
        return [
            'equals' => ['='],
            'plus' => ['+'],
            'minus' => ['-'],
            'at' => ['@'],
            'tab' => ["\t"],
            'carriage return' => ["\r"],
        ];
    }

    public function test_isFormulaInjection_is_false_for_ordinary_values(): void
    {
        $this->assertFalse(CsvSafety::isFormulaInjection('Rasim'));
        $this->assertFalse(CsvSafety::isFormulaInjection('rasim@example.com'));
    }

    public function test_isFormulaInjection_is_false_for_null_and_empty_string(): void
    {
        $this->assertFalse(CsvSafety::isFormulaInjection(null));
        $this->assertFalse(CsvSafety::isFormulaInjection(''));
    }

    public function test_sanitizeForExport_prefixes_a_single_quote_when_triggered(): void
    {
        $this->assertSame("'=1+1", CsvSafety::sanitizeForExport('=1+1'));
    }

    public function test_sanitizeForExport_leaves_ordinary_values_unchanged(): void
    {
        $this->assertSame('Rasim', CsvSafety::sanitizeForExport('Rasim'));
    }

    public function test_sanitizeForExport_passes_through_null(): void
    {
        $this->assertNull(CsvSafety::sanitizeForExport(null));
    }
}
