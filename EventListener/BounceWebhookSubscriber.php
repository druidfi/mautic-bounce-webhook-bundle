<?php declare(strict_types=1);

namespace MauticPlugin\MauticBounceWebhookBundle\EventListener;

use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\TransportWebhookEvent;
use MauticPlugin\MauticBounceWebhookBundle\Provider\BounceProviderRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

class BounceWebhookSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BounceProviderRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::ON_TRANSPORT_WEBHOOK => 'onTransportWebhook',
        ];
    }

    public function onTransportWebhook(TransportWebhookEvent $event): void
    {
        $request = $event->getRequest();

        try {
            $provider = $this->registry->getActive();
        } catch (\RuntimeException $e) {
            $this->logger->error('MauticBounceWebhookBundle: ' . $e->getMessage());

            return;
        }

        // Let other subscribers handle requests not recognised by the active provider.
        if (!$provider->supports($request)) {
            return;
        }

        if (!$this->isAuthorized($request->query->get('token', ''))) {
            $this->logger->warning('MauticBounceWebhookBundle: Webhook request rejected — invalid or missing secret token.');
            $event->setResponse(new Response('Unauthorized', Response::HTTP_UNAUTHORIZED));

            return;
        }

        $provider->process($request);
        $event->setResponse(new Response('', Response::HTTP_OK));
    }

    /**
     * Constant-time comparison to prevent timing attacks.
     * When MAILER_CALLBACK_SECRET is not set the endpoint is unrestricted (useful for local dev).
     */
    private function isAuthorized(string $token): bool
    {
        $secret = (string) (getenv('MAILER_CALLBACK_SECRET') ?: '');

        if ($secret === '') {
            return true;
        }

        return hash_equals($secret, $token);
    }
}
