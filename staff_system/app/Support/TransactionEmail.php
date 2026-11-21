<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class TransactionEmail
{
    public static function sendAfterCommit(User $recipient, string $subject, string $body, array $context): void
    {
        if (!($recipient->preferences['email'] ?? true)) {
            return;
        }

        DB::afterCommit(function () use ($recipient, $subject, $body, $context) {
            $mailer = config('mail.default');
            if (!app()->environment(['local', 'testing']) && in_array($mailer, ['log', 'array'], true)) {
                Log::warning('Transaction email requires a delivery mailer', [...$context, 'mailer' => $mailer]);
                return;
            }

            try {
                Mail::raw($body, fn ($message) => $message->to($recipient->email)->subject($subject));
            } catch (Throwable $error) {
                Log::warning('Transaction email delivery failed', [
                    ...$context,
                    'mailer' => $mailer,
                    'exception_type' => $error::class,
                    'exception_code' => $error->getCode(),
                ]);
            }
        });
    }
}
