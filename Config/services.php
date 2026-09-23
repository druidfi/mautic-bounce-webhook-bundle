<?php declare(strict_types=1);

use MauticPlugin\MauticBounceWebhookBundle\Provider\TransportCallbackAdapter;
use MauticPlugin\MauticBounceWebhookBundle\Provider\TransportCallbackInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    // Load all bundle services (mirrors Mautic's default auto-registration).
    $services->load('MauticPlugin\\MauticBounceWebhookBundle\\', '../')
        ->exclude('../{Config,Tests,vendor}');

    // Bind the interface so Symfony injects the adapter everywhere it is type-hinted.
    $services->alias(TransportCallbackInterface::class, TransportCallbackAdapter::class);
};
