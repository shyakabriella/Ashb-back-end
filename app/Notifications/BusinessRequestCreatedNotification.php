<?php

namespace App\Notifications;

use App\Models\BusinessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessRequestCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public BusinessRequest $businessRequest
    ) {
        $this->businessRequest->loadMissing([
            'property:id,title,price,address,location',
            'requester:id,first_name,last_name,email',
        ]);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $requestCode = $this->businessRequest->request_code ?: 'New Request';

        return (new MailMessage)
            ->subject('New ASHBHUB Request Created - ' . $requestCode)
            ->view('email.business-request-created', [
                'businessRequest' => $this->businessRequest,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'request_id' => $this->businessRequest->id,
            'request_code' => $this->businessRequest->request_code,
            'title' => $this->businessRequest->title,
            'amount' => (float) $this->businessRequest->amount,
            'status' => $this->businessRequest->status,
        ];
    }
}