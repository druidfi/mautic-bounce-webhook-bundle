<?php declare(strict_types=1);

namespace MauticPlugin\MauticBounceWebhookBundle\Provider;

/**
 * Selects the active bounce provider based on the MAILER_BOUNCE_PROVIDER env var.
 *
 * To add a new provider:
 *   1. Create a class implementing BounceProviderInterface
 *   2. Add it as a constructor argument here
 *   3. Register it in $this->providers with a matching key
 *
 * Available values for MAILER_BOUNCE_PROVIDER:
 *   mailjet → MailjetBounceProvider
 */
class BounceProviderRegistry
{
    /** @var array<string, BounceProviderInterface> */
    private array $providers;

    public function __construct(
        MailjetBounceProvider $mailjet,
    ) {
        $this->providers = [
            'mailjet' => $mailjet,
        ];
    }

    /**
     * Returns the provider configured via MAILER_BOUNCE_PROVIDER.
     *
     * @throws \RuntimeException when the env var is missing or names an unknown provider.
     */
    public function getActive(): BounceProviderInterface
    {
        $name = strtolower((string) (getenv('MAILER_BOUNCE_PROVIDER') ?: ''));

        if ($name === '') {
            throw new \RuntimeException(
                'MAILER_BOUNCE_PROVIDER env var is not set. Available providers: '
                . implode(', ', array_keys($this->providers))
            );
        }

        if (!isset($this->providers[$name])) {
            throw new \RuntimeException(sprintf(
                'Unknown bounce provider "%s". Available providers: %s',
                $name,
                implode(', ', array_keys($this->providers))
            ));
        }

        return $this->providers[$name];
    }
}
