<?php

namespace Omnishield\Exception;

/**
 * The provider answered with an error rather than a verdict: a request it
 * could not read, an alert on the account. Not a refusal - a token that does
 * not hold is a Verdict that did not pass - and not a silence either
 * (UnreachableException).
 */
class ProviderException extends \RuntimeException implements OmnishieldException
{
    public function __construct(
        public readonly string $provider,
        string $message,
        /** The provider's own error code, when it gave one */
        public readonly ?string $providerCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('[%s] %s', $provider, $message), 0, $previous);
    }
}
