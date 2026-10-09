<?php

/**
 * The providers as their packages' tests recorded them: a MockHttpClient
 * callback that answers each call from the Tests/Fixtures of the package
 * that makes it. What bare and the console use unless told --live: nothing
 * leaves the machine.
 *
 * @return Closure(string, string, array<string, mixed>): Symfony\Component\HttpClient\Response\MockResponse
 */

use Symfony\Component\HttpClient\Response\MockResponse;

$fixtures = static function (string $factory): string {
    return \dirname((string) (new ReflectionClass($factory))->getFileName()).'/Tests/Fixtures/';
};
$json = static fn (string $file): MockResponse => new MockResponse((string) file_get_contents($file), ['response_headers' => ['content-type' => 'application/json']]);

return static function (string $method, string $url, array $options) use ($fixtures, $json): MockResponse {
    $body = [];
    if (\is_string($options['body'] ?? null)) {
        str_starts_with($options['body'], '{') ? $body = (array) json_decode($options['body'], true) : parse_str($options['body'], $body);
    }
    $host = (string) parse_url($url, \PHP_URL_HOST);
    $path = (string) parse_url($url, \PHP_URL_PATH);

    if ('challenges.cloudflare.com' === $host) {
        $dir = $fixtures('Omnishield\\Turnstile\\TurnstileGatewayFactory');

        return $json($dir.match (substr((string) ($body['secret'] ?? ''), 0, 2)) {
            '1x' => 'siteverify-testing-passes.json',
            '2x' => 'siteverify-testing-fails.json',
            '3x' => 'siteverify-testing-spent.json',
            default => 'siteverify-invalid-secret.json',
        });
    }
    if (str_ends_with($path, '/recaptcha/api/siteverify')) {
        $dir = $fixtures('Omnishield\\Recaptcha\\RecaptchaGatewayFactory');

        return $json($dir.('6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe' === ($body['secret'] ?? null) ? 'siteverify-test-keys.json' : 'siteverify-unknown-secret.json'));
    }
    if ('rest.akismet.com' === $host) {
        $dir = $fixtures('Omnishield\\Akismet\\AkismetGatewayFactory');
        $spam = str_contains(($body['comment_author'] ?? '').' '.($body['comment_content'] ?? ''), 'akismet-guaranteed-spam');
        $file = match (basename($path)) {
            'comment-check' => $spam ? 'comment-check-discard' : 'comment-check-ham',
            'verify-key' => 'verify-key-valid',
            default => 'submit-thanks',
        };
        $fixture = json_decode((string) file_get_contents($dir.$file.'.json'), true);

        return new MockResponse($fixture['body'], ['response_headers' => $fixture['headers']]);
    }
    if (str_ends_with($host, 'stopforumspam.org')) {
        $dir = $fixtures('Omnishield\\Stopforumspam\\StopforumspamGatewayFactory');

        return $json($dir.match (true) {
            '185.220.101.1' === ($body['ip'] ?? null) => 'lookup-tor-exit.json',
            isset($body['emailhash']) => 'lookup-emailhash.json',
            default => 'lookup-documentation-values.json',
        });
    }

    return new MockResponse('', ['http_code' => 404]);
};
