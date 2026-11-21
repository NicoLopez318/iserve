<?php

namespace App\Support;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\TextPart;

final class PhpMailTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        $recipients = $message->getEnvelope()->getRecipients();

        if (!$email instanceof Email || count($recipients) !== 1 || $email->getCc() || $email->getBcc()) {
            throw new TransportException('PHP mail supports iServe messages with one direct recipient.');
        }

        $headers = $email->getPreparedHeaders();
        $subject = $headers->get('Subject')?->getBodyAsString() ?? '';
        $headers->remove('Subject');
        $headers->remove('To');
        $headers->remove('Message-ID');
        $headers->addIdHeader('Message-ID', $message->getMessageId());

        $body = $email->getBody() ?? new TextPart('');
        $mimeHeaders = $headers->toString().$body->getPreparedHeaders()->toString();

        if (!function_exists('mail') || !mail(
            $recipients[0]->getEncodedAddress(),
            $subject,
            $body->bodyToString(),
            rtrim($mimeHeaders, "\r\n"),
        )) {
            throw new TransportException('The PHP mail service did not accept the message.');
        }
    }

    public function __toString(): string
    {
        return 'php_mail';
    }
}
