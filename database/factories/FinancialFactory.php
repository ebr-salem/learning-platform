<?php

namespace Database\Factories;

use App\Models\Financial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Financial>
 */
class FinancialFactory extends Factory
{
    protected $model = Financial::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'user_id' => User::factory()->student(),
            'created_by' => User::factory()->assistant(),
        ];
    }
}
