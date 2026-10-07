<?php

/**
 * The gateways, their options from the environment (.env): a gateway is
 * configured only when every key under "needs" is set. "example" is what
 * the bare script and the console's --example use instead: the providers'
 * own testing keys - Cloudflare's dummy keys, Google's v2 test keys - and,
 * for Akismet, a key that only the recorded answers accept.
 *
 * @return array<string, array{factory: string, needs: list<string>, options: array<string, mixed>, example: array<string, mixed>}>
 */
$env = static fn (string $key, mixed $default = null): mixed => (false !== ($v = getenv($key)) && '' !== $v) ? $v : $default;

return [
    'altcha' => [
        'factory' => 'altcha',
        'needs' => ['ALTCHA_HMAC_KEY'],
        'options' => ['hmac_key' => $env('ALTCHA_HMAC_KEY')],
        // A low cost: the harness solves its own challenges, in PHP.
        'example' => ['hmac_key' => 'the-harness-s-example-key-not-a-secret', 'cost' => 100],
    ],
    'turnstile' => [
        'factory' => 'turnstile',
        'needs' => ['TURNSTILE_SITE_KEY', 'TURNSTILE_SECRET'],
        'options' => ['site_key' => $env('TURNSTILE_SITE_KEY'), 'secret' => $env('TURNSTILE_SECRET')],
        'example' => ['site_key' => '1x00000000000000000000AA', 'secret' => '1x0000000000000000000000000000000AA'],
    ],
    'recaptcha' => [
        'factory' => 'recaptcha',
        'needs' => ['RECAPTCHA_SITE_KEY'],
        'options' => ['mode' => $env('RECAPTCHA_MODE', 'checkbox'), 'site_key' => $env('RECAPTCHA_SITE_KEY'), 'secret' => $env('RECAPTCHA_SECRET'), 'project_id' => $env('RECAPTCHA_PROJECT_ID'), 'api_key' => $env('RECAPTCHA_API_KEY')],
        'example' => ['mode' => 'checkbox', 'site_key' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI', 'secret' => '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe'],
    ],
    'akismet' => [
        'factory' => 'akismet',
        'needs' => ['AKISMET_API_KEY', 'AKISMET_SITE'],
        'options' => ['api_key' => $env('AKISMET_API_KEY'), 'site' => $env('AKISMET_SITE'), 'test' => $env('AKISMET_TEST', '1')],
        'example' => ['api_key' => 'the-recorded-answers-key', 'site' => 'https://example.org', 'test' => true],
    ],
    'stopforumspam' => [
        'factory' => 'stopforumspam',
        'needs' => [],
        'options' => ['threshold' => $env('STOPFORUMSPAM_THRESHOLD', 50), 'api_key' => $env('STOPFORUMSPAM_API_KEY')],
        'example' => [],
    ],
    'disposable' => [
        'factory' => 'disposable',
        'needs' => [],
        'options' => [],
        'example' => [],
    ],
    'fixed' => [
        'factory' => 'fixed',
        'needs' => [],
        'options' => [],
        'example' => [],
    ],
];
