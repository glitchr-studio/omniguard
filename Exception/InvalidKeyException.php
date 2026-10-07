<?php

namespace Omniguard\Exception;

/**
 * The provider refused the site's key or secret: the configuration is wrong
 * (a key mistyped, revoked, for another site), whatever the visitor did.
 */
final class InvalidKeyException extends ProviderException
{
}
