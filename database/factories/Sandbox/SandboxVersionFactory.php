<?php

namespace Database\Factories\Sandbox;

use App\Models\Sandbox\Sandbox;
use App\Models\Sandbox\SandboxVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SandboxVersionFactory extends Factory
{
    protected $model = SandboxVersion::class;

    public function definition(): array
    {
        return [
            'sandbox_id' => Sandbox::factory(),
            'user_id' => User::factory(),
            'title' => $this->faker->optional()->sentence(2),
            'content' => '<p>' . $this->faker->paragraph() . '</p>',
        ];
    }
}
