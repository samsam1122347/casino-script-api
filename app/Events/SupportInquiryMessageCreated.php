<?php

namespace App\Events;

use App\Http\Controllers\Api\V1\SupportGuestBroadcastAuthController;
use App\Models\SupportInquiryMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tenant-scoped private channel: {@see routes/channels.php} `support-inquiries.{id}`.
 * Players authorize via Sanctum or guest {@see SupportGuestBroadcastAuthController}.
 */
class SupportInquiryMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SupportInquiryMessage $message) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support-inquiries.'.$this->message->support_inquiry_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'SupportInquiryMessage';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->message->loadMissing('admin:id,name');

        return [
            'id' => (string) $this->message->getKey(),
            'support_inquiry_id' => (string) $this->message->support_inquiry_id,
            'body' => $this->message->body,
            'is_from_admin' => $this->message->is_from_admin,
            'admin_name' => $this->message->admin?->name,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
