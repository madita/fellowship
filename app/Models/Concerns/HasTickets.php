<?php

namespace App\Models\Concerns;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasTickets
{
    /**
     * Get all tickets for this model.
     */
    public function tickets(): MorphMany
    {
        return $this->morphMany(Ticket::class, 'ticketable');
    }

    /**
     * Create a ticket for this model.
     */
    public function createTicket(array $data): Ticket
    {
        return $this->tickets()->create($data);
    }

    /**
     * Auto-create ticket by type slug.
     */
    public function autoCreateTicket(string $typeSlug, array $data = []): ?Ticket
    {
        $ticketType = TicketType::findAutoCreateBySlug($typeSlug);

        if (!$ticketType) {
            return null;
        }

        $defaultData = [
            'ticket_type_id' => $ticketType->id,
            'title' => $data['title'] ?? $this->getTicketTitle(),
            'description' => $data['description'] ?? $this->getTicketDescription(),
            'status' => 'open',
            'priority' => $data['priority'] ?? 'normal',
        ];

        return $this->createTicket(array_merge($defaultData, $data));
    }

    /**
     * Get default ticket title (override in model if needed).
     */
    protected function getTicketTitle(): string
    {
        $modelName = class_basename($this);
        return "New {$modelName}: " . ($this->title ?? $this->name ?? $this->id);
    }

    /**
     * Get default ticket description (override in model if needed).
     */
    protected function getTicketDescription(): string
    {
        return '';
    }

    /**
     * Check if this model has any open tickets.
     */
    public function hasOpenTickets(): bool
    {
        return $this->tickets()->open()->exists();
    }

    /**
     * Get open tickets count.
     */
    public function openTicketsCount(): int
    {
        return $this->tickets()->open()->count();
    }
}
