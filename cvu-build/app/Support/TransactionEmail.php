<?php

namespace App\Support;

use App\Models\{ServiceRequest, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

    public static function receipt(ServiceRequest $request, bool $resubmitted = false): void
    {
        if ($request->office() !== 'cvu_head') {
            return;
        }

        $action = $resubmitted ? 'resubmitted' : 'received';
        $recipient = $request->user;
        $path = '/requests/'.$request->id;
        $subject = 'iServe request '.$action.': '.$request->ticket;
        $confirmation = "Your request {$request->ticket} was {$action} and is pending review.";

        if ($recipient->preferences['in_app'] ?? true) {
            $recipient->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'request',
                'data' => ['title' => $subject, 'url' => $path, 'message' => $confirmation],
            ]);
        }

        $requestUrl = rtrim(config('app.url'), '/').$path;
        self::sendAfterCommit(
            $recipient,
            $subject,
            "{$confirmation}\n\nView your request: {$requestUrl}",
            ['request_id' => $request->id],
        );
    }
}
