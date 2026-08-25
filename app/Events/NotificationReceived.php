<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $notification;

    public function __construct($userId, $notification)
    {
        $this->userId = $userId;
        $this->notification = $notification;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('app-notifications.' . $this->userId),
        ];
    }
}
