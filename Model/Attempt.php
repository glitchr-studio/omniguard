<?php

namespace Omnishield\Model;

/**
 * A token to check, and what it should match: the visitor's address (given
 * to providers that take it), the action the form was for, the host the
 * page was served from.
 */
final readonly class Attempt
{
    /**
     * @param string      $token    what the widget posted; "" when nothing was
     * @param string|null $ip       the visitor's address, as the application sees it (behind its proxies)
     * @param string|null $action   the action the token must have been made for; null: not checked
     * @param string|null $hostname the host the widget must have been shown on; null: not checked
     */
    public function __construct(
        public string $token,
        public ?string $ip = null,
        public ?string $action = null,
        public ?string $hostname = null,
    ) {
    }

    /**
     * The token from a form's posted fields ($_POST, or a framework's
     * request), under the widget's field.
     *
     * @param array<string, mixed> $post
     */
    public static function fromPost(array $post, Widget $widget, ?string $ip = null, ?string $hostname = null): self
    {
        $token = $post[$widget->field] ?? '';

        return new self(\is_string($token) ? trim($token) : '', $ip, $widget->action, $hostname);
    }

    public function isEmpty(): bool
    {
        return '' === $this->token;
    }
}
