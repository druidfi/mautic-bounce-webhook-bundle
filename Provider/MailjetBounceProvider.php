<?php declare(strict_types=1);

namespace MauticPlugin\MauticBounceWebhookBundle\Provider;

use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\LeadBundle\Entity\DoNotContact;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles bounce/blocked/spam callbacks from Mailjet's Event API.
 *
 * Mailjet sends a JSON array of event objects to the configured callback URL.
 * Each object contains at minimum an `event` string and an `email` string.
 *
 * @see https://dev.mailjet.com/email/guides/webhooks/
 */
class MailjetBounceProvider implements BounceProviderInterface
{
    /**
     * All event types Mailjet's Event API can send.
     * Used to fingerprint incoming requests as Mailjet payloads.
     */
    private const MAILJET_EVENT_TYPES = ['sent', 'open', 'click', 'bounce', 'blocked', 'spam', 'unsub'];

    public function __construct(
        private readonly TransportCallback $transportCallback,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supports(Request $request): bool
    {
        $payload = $this->decodePayload($request);

        return $payload !== null
            && isset($payload[0]['event'])
            && in_array($payload[0]['event'], self::MAILJET_EVENT_TYPES, true)
            && array_key_exists('hard_bounce', $payload[0]); // Mailjet-specific field
    }

    public function process(Request $request): void
    {
        $payload = $this->decodePayload($request);

        if ($payload === null) {
            return;
        }

        foreach ($payload as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type  = (string) ($item['event'] ?? '');
            $email = (string) ($item['email'] ?? '');

            if ($email === '') {
                continue;
            }

            match ($type) {
                'bounce'  => $this->processBounce($item),
                'blocked' => $this->processBlocked($item),
                'spam'    => $this->processSpam($item),
                default   => null,
            };
        }
    }

    private function processBounce(array $event): void
    {
        $email        = (string) $event['email'];
        $isHardBounce = (bool) ($event['hard_bounce'] ?? false);

        if (!$isHardBounce) {
            $this->logger->info(
                'MauticBounceWebhookBundle [Mailjet]: Soft bounce for {email} — skipping DNC.',
                ['email' => $email],
            );

            return;
        }

        $error   = (string) ($event['error'] ?? 'hard bounce');
        $comment = sprintf('Mailjet hard bounce: %s', $error);

        $this->logger->info(
            'MauticBounceWebhookBundle [Mailjet]: Hard bounce for {email}: {error}',
            ['email' => $email, 'error' => $error],
        );

        $this->transportCallback->addFailureByAddress($email, $comment, DoNotContact::BOUNCED);
    }

    private function processBlocked(array $event): void
    {
        $email   = (string) $event['email'];
        $error   = (string) ($event['error'] ?? 'blocked');
        $comment = sprintf('Mailjet blocked: %s', $error);

        $this->logger->info(
            'MauticBounceWebhookBundle [Mailjet]: Blocked email for {email}: {error}',
            ['email' => $email, 'error' => $error],
        );

        $this->transportCallback->addFailureByAddress($email, $comment, DoNotContact::BOUNCED);
    }

    private function processSpam(array $event): void
    {
        $email   = (string) $event['email'];
        $comment = 'Mailjet spam complaint';

        $this->logger->info(
            'MauticBounceWebhookBundle [Mailjet]: Spam complaint for {email}.',
            ['email' => $email],
        );

        $this->transportCallback->addFailureByAddress($email, $comment, DoNotContact::UNSUBSCRIBED);
    }

    /**
     * @return array<mixed>|null
     */
    private function decodePayload(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload) || empty($payload)) {
            return null;
        }

        return $payload;
    }
}
