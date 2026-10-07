<?php

namespace Omniguard\Tests;

use Omniguard\Exception\ProviderException;
use Omniguard\Exception\UnreachableException;
use Omniguard\Http\Answer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AnswerTest extends TestCase
{
    public function testAnAnswerIsReadWholeWhateverItsStatus(): void
    {
        $answer = Answer::send(new MockHttpClient(new MockResponse('{"error":"bad"}', ['http_code' => 400, 'response_headers' => ['X-Akismet-Debug-Help' => 'Empty "blog" value']])), 'p', 'POST', 'https://p.example/x');

        self::assertSame(400, $answer->status);
        self::assertSame('Empty "blog" value', $answer->header('x-akismet-debug-help'));
        self::assertSame(['error' => 'bad'], $answer->json());
        self::assertNull($answer->header('nope'));
    }

    public function testNoAnswerAServerErrorOrAQuotaIsUnreachable(): void
    {
        foreach ([
            'no connection' => new MockResponse('', ['error' => 'Could not resolve host']),
            'server error' => new MockResponse('oops', ['http_code' => 503]),
            'quota' => new MockResponse('slow down', ['http_code' => 429]),
        ] as $case => $response) {
            try {
                Answer::send(new MockHttpClient($response), 'p', 'GET', 'https://p.example/x');
                self::fail($case);
            } catch (UnreachableException $e) {
                self::assertSame('p', $e->provider, $case);
            }
        }
    }

    public function testABodyThatIsNotJsonIsTheProvidersError(): void
    {
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('[p] HTTP 200, not a JSON object: <html>');
        Answer::send(new MockHttpClient(new MockResponse('<html>')), 'p', 'GET', 'https://p.example/x')->json();
    }
}
