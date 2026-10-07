<?php

namespace Omniguard\Bridge\Symfony\EventListener;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * The scripts a gateway package ships, served by the site itself - the
 * ALTCHA widget (omniguard/altcha's public/altcha.min.js) at
 * /omniguard/altcha/3.3.0/altcha.min.js - so that the page reaches nobody:
 * no CDN sees the visitor. Answered before routing, with no route to import
 * and no asset pipeline (neither Webpack Encore nor AssetMapper); its address
 * carries the version, so it is cached for a year.
 */
final class ScriptListener
{
    /** @param array<string, string> $scripts the path asked => the file */
    public function __construct(private readonly array $scripts)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethodSafe()) {
            return;
        }
        $file = $this->scripts[$request->getPathInfo()] ?? null;
        if (null === $file || !is_file($file)) {
            return;
        }

        $response = new BinaryFileResponse($file, 200, ['Content-Type' => 'text/javascript; charset=utf-8'], true, null, false, true);
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->headers->addCacheControlDirective('immutable');
        $response->isNotModified($request);
        $event->setResponse($response);
    }
}
