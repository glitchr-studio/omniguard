<?php

namespace Omnishield\Bridge\Symfony\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * The value is a token the captcha accepts: posted, valid, unused, for this
 * action and host when they are given.
 *
 *     #[PassesChallenge(gateway: 'forms', action: 'contact')]
 *     public ?string $captcha = null;
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PassesChallenge extends Constraint
{
    public const MISSING_ERROR = 'b8a5d4c2-6f0e-4b1a-9d3c-0e7f1a2b3c41';
    public const FAILED_ERROR = 'b8a5d4c2-6f0e-4b1a-9d3c-0e7f1a2b3c42';
    public const UNREACHABLE_ERROR = 'b8a5d4c2-6f0e-4b1a-9d3c-0e7f1a2b3c43';

    protected const ERROR_NAMES = [
        self::MISSING_ERROR => 'MISSING_ERROR',
        self::FAILED_ERROR => 'FAILED_ERROR',
        self::UNREACHABLE_ERROR => 'UNREACHABLE_ERROR',
    ];

    public string $missingMessage = 'Please confirm that you are not a robot.';
    public string $message = 'The check that you are not a robot did not pass. Please try again.';
    public string $unreachableMessage = 'The check that you are not a robot could not be done just now. Please try again in a moment.';

    /**
     * @param string|null      $gateway     the configured captcha; omnishield.challenge.gateway when null
     * @param string|null      $action      the action the token must have been made for
     * @param string|bool|null $hostname    the host the widget must have been shown on; true: the request's
     * @param bool|null        $unreachable true lets the value through when the provider does not answer; null: omnishield.challenge.unreachable
     */
    public function __construct(
        public ?string $gateway = null,
        public ?string $action = null,
        public string|bool|null $hostname = null,
        public ?bool $unreachable = null,
        ?string $message = null,
        ?string $missingMessage = null,
        ?string $unreachableMessage = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
        $this->message = $message ?? $this->message;
        $this->missingMessage = $missingMessage ?? $this->missingMessage;
        $this->unreachableMessage = $unreachableMessage ?? $this->unreachableMessage;
    }
}
