<?php

namespace App\Enums;

use App\Attributes\Description;
use App\Traits\AttributableEnum;

/**
 * Phase 9 (quality tooling): `description()` only exists at runtime via
 * AttributableEnum's `__call()` (reflection over each case's `#[Description]`
 * attribute) -- there is no literal method declaration for PHPStan/Larastan
 * to see. This is PHPStan's own documented pattern for a magic `__call`
 * method, not a real-code change.
 *
 * @method string description()
 */
enum UserStatus: string
{
    use AttributableEnum;

    #[Description('Yeni Qeydiyyat')]
    case NEW_REGISTER = 'new_register';
    #[Description('İşləyir')]
    case IT_WORKS = 'it_works';
    #[Description('Məzuniyyət')]
    case VACATION = 'vacation';
    #[Description('Analıq məzuniyyəti')]
    case MATERNITY_LEAVE = 'maternity_leave';
    #[Description('Təhsil')]
    case EDUCATION = 'education';
    #[Description('Ezamiyyət')]
    case BUSINESS_TRIP = 'business_trip';
    #[Description('Çıxıb')]
    case OUT = 'out';
    #[Description('Çıxarılıb')]
    case REMOVED = 'removed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
