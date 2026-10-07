<?php

namespace Omniguard\Exception;

/**
 * The provider did not answer: no connection, a timeout, a server error, a
 * quota exceeded. Nothing was decided about the token, the content or the
 * identity - whether to let the form through is the application's call.
 */
final class UnreachableException extends ProviderException
{
}
