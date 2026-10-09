<?php

namespace Omnishield\Model;

/**
 * What a visitor submitted, as a classifier reads it: the text, who wrote
 * it, from where. Strings and one date - never an account, an entity or a
 * request: the application fills it from its own.
 */
final readonly class Submission
{
    public const COMMENT = 'comment';
    public const REPLY = 'reply';
    public const FORUM_POST = 'forum-post';
    public const BLOG_POST = 'blog-post';
    public const CONTACT_FORM = 'contact-form';
    public const SIGNUP = 'signup';
    public const MESSAGE = 'message';

    /**
     * @param string                  $content   what was written
     * @param string|null             $author    the name given
     * @param string|null             $email     the e-mail given
     * @param string|null             $url       the address the author gave as theirs
     * @param string|null             $ip        the visitor's address
     * @param string|null             $userAgent the browser's User-Agent
     * @param string|null             $referrer  the page the visitor came from (Referer)
     * @param string|null             $permalink the page the content was submitted on, or will be shown at
     * @param string|null             $site      the site's home page: "https://example.org"
     * @param string|null             $language  the site's language: "fr", "fr_FR"; several, comma-separated
     * @param string                  $type      what it is: the constants above
     * @param \DateTimeImmutable|null $date      when it was submitted
     */
    public function __construct(
        public string $content,
        public ?string $author = null,
        public ?string $email = null,
        public ?string $url = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $referrer = null,
        public ?string $permalink = null,
        public ?string $site = null,
        public ?string $language = null,
        public string $type = self::COMMENT,
        public ?\DateTimeImmutable $date = null,
    ) {
    }

    /** The submitter as a list reads them. */
    public function identity(): Identity
    {
        return new Identity($this->ip, $this->email, $this->author);
    }
}
