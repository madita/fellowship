<?php

namespace Database\Factories\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'ticket_type_id' => TicketType::factory(),
            'created_by_user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'status' => 'open',
            'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn () => [
            'assigned_to_user_id' => $user->id,
            'status' => 'in_progress',
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => [
            'created_by_user_id' => $user->id,
        ]);
    }
}
