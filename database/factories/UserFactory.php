<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserModel>
 */
class UserFactory extends Factory
{
    protected $model = \App\Models\UserModel::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = time();

        return [
            'account' => fake()->unique()->bothify('??####'),
            'real_name' => fake()->name(),
            'phone' => null,
            'password' => static::$password ??= Hash::make('password'),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
