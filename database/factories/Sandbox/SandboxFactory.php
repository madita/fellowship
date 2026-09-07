<?php

namespace Database\Factories\Sandbox;

use App\Models\Sandbox\Sandbox;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SandboxFactory extends Factory
{
    protected $model = Sandbox::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'uuid' => (string) Str::uuid(),
            'slug' => $this->faker->unique()->slug(3),
            'description' => $this->faker->optional()->paragraph(),
            'user_id' => User::factory(),
            'visibility' => 'private',
            'content' => '<p>' . $this->faker->paragraph() . '</p>',
            'settings' => [
                'allowComments' => true,
                'showCursors' => true,
                'autoSave' => true,
            ],
            'last_edited_at' => now(),
        ];
    }

    public function public(): static
    {
        return $this->state(fn() => ['visibility' => 'public']);
    }

    public function members(): static
    {
        return $this->state(fn() => ['visibility' => 'members']);
    }

    public function private(): static
    {
        return $this->state(fn() => ['visibility' => 'private']);
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn() => ['user_id' => $user->id]);
    }
}
