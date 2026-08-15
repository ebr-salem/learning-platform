<?php

namespace Database\Factories;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'student_code' => 'STU-'.fake()->unique()->numberBetween(1000, 9999),
            'grade' => fake()->randomElement(['Grade 3', 'Grade 4', 'Grade 5', 'Grade 6']),
            'profile_image' => fake()->optional()->imageUrl(),
            'qr_code_string' => Str::uuid()->toString(),
            'dob' => fake()->dateTimeBetween('-14 years', '-6 years')->format('Y-m-d'),
            'guardian_name' => fake()->name('male'),
            'guardian_phone' => fake()->numerify('01##########'),
        ];
    }
}
