<?php

namespace App\Observers;

use App\Events\SupportInquiryMessageCreated;
use App\Models\SupportInquiryMessage;
use Illuminate\Support\Facades\Log;

class SupportInquiryMessageObserver
{
    public function created(SupportInquiryMessage $message): void
    {
        if (! $message->is_from_admin) {
            return;
        }

        // SupportInquiryMessageCreated is ShouldBroadcastNow — it runs synchronously
        // during the request. A Soketi/Pusher outage or credential mismatch must NOT
        // bubble up and fail the admin's reply: the player thread also polls, so the
        // message still reaches them. Log and move on.
        try {
            broadcast(new SupportInquiryMessageCreated($message));
        } catch (\Throwable $e) {
            Log::channel('support')->warning('Support reply broadcast failed; reply still saved.', [
                'support_inquiry_message_id' => (string) $message->getKey(),
                'support_inquiry_id' => (string) $message->support_inquiry_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
