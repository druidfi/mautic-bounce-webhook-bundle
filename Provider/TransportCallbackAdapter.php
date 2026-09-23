<?php declare(strict_types=1);

namespace MauticPlugin\MauticBounceWebhookBundle\Provider;

use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\LeadBundle\Entity\DoNotContact;

/**
 * Adapts Mautic's final TransportCallback class to TransportCallbackInterface.
 */
class TransportCallbackAdapter implements TransportCallbackInterface
{
    public function __construct(private readonly TransportCallback $inner)
    {
    }

    public function addFailureByAddress(string $address, ?string $comments, int $dncReason = DoNotContact::BOUNCED): void
    {
        $this->inner->addFailureByAddress($address, $comments, $dncReason);
    }
}
