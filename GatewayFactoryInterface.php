<?php

namespace Omnishield;

/** Builds a gateway from its options (a site key, a secret, a threshold...). */
interface GatewayFactoryInterface
{
    /** The name gateways are configured with: "altcha", "turnstile"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): GatewayInterface;
}
