<?php

namespace Omniguard\Exception;

/** A gateway misconfigured: an option missing, an unknown factory, a submission without what the provider needs. */
final class InvalidConfigException extends \LogicException implements OmniguardException
{
}
