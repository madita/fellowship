<?php

namespace Database\Factories\Ticket;

use App\Models\Ticket\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'icon' => 'mdi-ticket',
            'color' => $this->faker->hexColor(),
            'is_active' => true,
            'auto_create' => false,
            'position' => $this->faker->numberBetween(0, 10),
        ];
    }
}
