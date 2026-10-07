<?php

namespace Omniguard\Bridge\Symfony\Controller;

use Omniguard\ChallengeIssuerInterface;
use Omniguard\Registry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A fresh challenge for a captcha the site issues itself (ALTCHA): what the
 * widget fetches when its gateway's challenge_url points here. Never cached.
 * A plain controller - no AbstractController, nothing of FrameworkBundle -
 * imported by the application:
 *
 *     // config/routes/omniguard.php
 *     return static fn (RoutingConfigurator $routes) => $routes->import(ChallengeController::class, 'attribute');
 *
 *     omniguard.gateways.forms.options.challenge_url: /omniguard/forms/challenge
 */
final class ChallengeController
{
    public function __construct(private readonly Registry $registry)
    {
    }

    #[Route('/omniguard/{gateway}/challenge', name: 'omniguard_challenge', requirements: ['gateway' => '[A-Za-z0-9_.-]+'], methods: ['GET'])]
    public function __invoke(string $gateway, Request $request): JsonResponse
    {
        $issuer = $this->registry->has($gateway) ? $this->registry->get($gateway) : null;
        if (!$issuer instanceof ChallengeIssuerInterface) {
            throw new NotFoundHttpException(\sprintf('No captcha "%s" that issues its challenges.', $gateway));
        }
        $action = $request->query->get('action');

        $response = new JsonResponse($issuer->issue(\is_string($action) && '' !== $action ? $action : null));
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
