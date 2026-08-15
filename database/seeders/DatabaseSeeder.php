<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Lesson;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Assistant',
            'username' => 'admin',
            'role' => UserRole::Assistant,
            'phone' => '01111111111',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        StudentProfile::factory()->create([
            'user_id' => User::factory()->create([
                'name' => 'Test Student',
                'username' => 'student1',
                'role' => UserRole::Student,
                'phone' => '01122222222',
                'email' => 'student1@example.com',
                'password' => 'password',
            ])->id,
            'student_code' => 'STU-0001',
            'qr_code_string' => 'test-qr-123',
        ]);

        StudentProfile::factory()
            ->count(10)
            ->create();

        Lesson::factory()
            ->count(5)
            ->create();
    }
}
