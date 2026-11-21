<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ListChanged implements ShouldBroadcastNow
{
    public function __construct(private string $listId, private array $participantIds) {}

    public function broadcastOn(): array
    {
        return array_map(fn ($id) => new PrivateChannel('lists.'.$this->listId.'.'.$id), $this->participantIds);
    }

    public function broadcastAs(): string
    {
        return 'list.changed';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
