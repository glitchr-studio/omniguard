<?php

namespace Omniguard\Model;

/** Who submits a form, as a list reads them: an address, an e-mail, a name. Any may be missing. */
final readonly class Identity
{
    public function __construct(
        public ?string $ip = null,
        public ?string $email = null,
        public ?string $name = null,
    ) {
    }

    /** The e-mail's domain, lower case, without a trailing dot; null without an e-mail. */
    public function domain(): ?string
    {
        if (null === $this->email || false === $at = strrpos($this->email, '@')) {
            return null;
        }
        $domain = rtrim(strtolower(trim(substr($this->email, $at + 1))), '.');

        return '' === $domain ? null : $domain;
    }

    public function isEmpty(): bool
    {
        return \in_array($this->ip, [null, ''], true) && \in_array($this->email, [null, ''], true) && \in_array($this->name, [null, ''], true);
    }
}
