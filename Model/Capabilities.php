<?php

namespace Omniguard\Model;

/**
 * What a gateway is, said before it is asked anything: which of the three
 * questions it answers, through whom, with what.
 */
final readonly class Capabilities
{
    /**
     * @param bool         $challenge  it answers "is this token valid?" (ChallengeInterface)
     * @param bool         $classifier it answers "is this content spam?" (ClassifierInterface)
     * @param bool         $reputation it answers "is this identity known for abuse?" (ReputationInterface)
     * @param bool         $thirdParty whether what it checks - a token, a text, an address - leaves the site for someone else's
     * @param bool         $cookies    whether its widget sets or reads cookies in the visitor's browser
     * @param bool         $scores     whether a Verdict carries a score
     * @param bool         $actions    whether it checks the action a token was made for
     * @param bool         $reports    whether it takes reports back (ClassifierInterface::report(), a list's own report())
     * @param list<string> $reads      what of an Identity a reputation reads: ip, email, name
     */
    public function __construct(
        public bool $challenge = false,
        public bool $classifier = false,
        public bool $reputation = false,
        public bool $thirdParty = true,
        public bool $cookies = false,
        public bool $scores = false,
        public bool $actions = false,
        public bool $reports = false,
        public array $reads = [],
    ) {
    }

    /** @return list<string> the questions it answers, by their contract: challenge, classifier, reputation */
    public function questions(): array
    {
        return array_keys(array_filter(['challenge' => $this->challenge, 'classifier' => $this->classifier, 'reputation' => $this->reputation]));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['questions' => $this->questions()] + get_object_vars($this);
    }
}
