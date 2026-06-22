<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password-reset email for storefront customers. Builds a store-scoped reset
 * URL (the {store} slug is auto-filled from URL::defaults set by the storefront
 * middleware during the request that triggers the email).
 */
class CustomerResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = route('shop.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Reset your password')
            ->greeting('Hello'.($notifiable->name ? ' '.$notifiable->name : '').',')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $url)
            ->line('This link will expire in 60 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
