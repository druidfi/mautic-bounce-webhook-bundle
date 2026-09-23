<?php declare(strict_types=1);

namespace MauticPlugin\MauticBounceWebhookBundle\Provider;

use Mautic\LeadBundle\Entity\DoNotContact;

interface TransportCallbackInterface
{
    /**
     * @param int $dncReason One of DoNotContact::BOUNCED or DoNotContact::UNSUBSCRIBED
     */
    public function addFailureByAddress(string $address, ?string $comments, int $dncReason = DoNotContact::BOUNCED): void;
}
