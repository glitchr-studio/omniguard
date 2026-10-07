<?php

namespace Omniguard\Model;

/** What a classifier said of a submission: ham, spam or flagrant spam, and why. */
final readonly class Classification
{
    /**
     * @param list<string>         $reasons what the provider gave as reasons or alerts, in its own words
     * @param array<string, mixed> $data    what else it answered (headers, a recheck delay)
     */
    public function __construct(
        public Label $label,
        public array $reasons = [],
        public array $data = [],
    ) {
    }

    public static function ham(): self
    {
        return new self(Label::HAM);
    }

    /** Spam, flagrant or not. */
    public function isSpam(): bool
    {
        return Label::HAM !== $this->label;
    }

    public function isFlagrant(): bool
    {
        return Label::FLAGRANT === $this->label;
    }
}
