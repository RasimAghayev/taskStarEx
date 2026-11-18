<?php

namespace App\Support\Csv;

/**
 * CSV-injection ("formula injection") defenses for the company-user CSV
 * export (Phase 8B, PR #90) and import endpoints. If a cell's raw value
 * begins with a formula-trigger character and the CSV is later opened in
 * Excel/Google Sheets, the spreadsheet application may execute it as a
 * formula instead of treating it as plain text -- see OWASP's CSV
 * Injection cheat sheet. `fin`/`name`/`surname`/`father`/`status` are
 * already constrained by regex/enum validation and cannot carry these
 * characters, but `email`'s local-part may legitimately start with `+`
 * or `-` under RFC 5321/5322, so this check is real, not redundant with
 * existing field validation.
 */
class CsvSafety
{
    private const TRIGGER_CHARS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * True if the raw value, written verbatim into a CSV cell, could be
     * interpreted as a formula by spreadsheet software.
     */
    public static function isFormulaInjection(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        foreach (self::TRIGGER_CHARS as $char) {
            if (str_starts_with($value, $char)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Neutralize a value for safe placement in an exported CSV cell: prefix
     * a risky value with a single quote so Excel/Sheets renders it as
     * literal text instead of evaluating it as a formula. Safe values are
     * returned unchanged. (Export-side mitigation only -- see import-side
     * handling in UserController::import(), which rejects rather than
     * silently rewrites a user's actual data on the way in.)
     */
    public static function sanitizeForExport(?string $value): ?string
    {
        if (self::isFormulaInjection($value)) {
            return "'".$value;
        }

        return $value;
    }
}
