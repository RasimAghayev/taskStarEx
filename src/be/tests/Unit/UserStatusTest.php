<?php

namespace Tests\Unit;

use App\Enums\UserStatus;
use PHPUnit\Framework\TestCase;

class UserStatusTest extends TestCase
{
    public function test_values_contains_enum_values(): void
    {
        $values = UserStatus::values();

        $this->assertContains('new_register', $values);
        $this->assertContains('it_works', $values);
    }
}
