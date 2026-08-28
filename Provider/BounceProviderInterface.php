<?php declare(strict_types=1);

namespace MauticPlugin\MauticBounceWebhookBundle\Provider;

use Symfony\Component\HttpFoundation\Request;

interface BounceProviderInterface
{
    /**
     * Returns true if this provider recognises the incoming webhook request.
     */
    public function supports(Request $request): bool;

    /**
     * Processes the webhook payload and records DNC entries via TransportCallback.
     */
    public function process(Request $request): void;
}
