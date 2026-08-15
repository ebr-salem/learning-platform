<?php

namespace Database\Factories;

use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chapter_name' => 'Chapter '.fake()->numberBetween(1, 10),
            'title' => fake()->sentence(4),
            'duration_minutes' => fake()->numberBetween(15, 90),
            'video_url' => 'https://www.youtube.com/watch?v='.fake()->regexify('[A-Za-z0-9_-]{11}'),
            'thumbnail_url' => 'https://img.youtube.com/vi/'.fake()->regexify('[A-Za-z0-9_-]{11}').'/hqdefault.jpg',
            'about_lesson' => fake()->paragraphs(2, true),
            'what_you_will_learn' => [
                fake()->sentence(6),
                fake()->sentence(6),
                fake()->sentence(6),
                fake()->sentence(6),
            ],
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
