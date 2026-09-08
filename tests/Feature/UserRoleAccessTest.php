<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function panel(): Panel
    {
        return Panel::make();
    }

    public function test_admin_can_access_the_panel(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($user->canAccessPanel($this->panel()));
    }

    public function test_assistant_can_access_the_panel(): void
    {
        $user = User::factory()->create(['role' => UserRole::Assistant]);

        $this->assertTrue($user->canAccessPanel($this->panel()));
    }

    public function test_student_cannot_access_the_panel(): void
    {
        $user = User::factory()->create(['role' => UserRole::Student]);

        $this->assertFalse($user->canAccessPanel($this->panel()));
    }
}
