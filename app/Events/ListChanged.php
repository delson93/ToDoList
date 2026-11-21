<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ListChanged implements ShouldBroadcastNow
{
    public function __construct(private string $listId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('lists.'.$this->listId)];
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
