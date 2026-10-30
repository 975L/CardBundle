<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Service;

use c975L\UiBundle\Service\RateLimiterGuard;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

// What every card call of a mobile app reads before anything else: the limit, the token and the json body, then answers privately. The routes and their payloads stay the site's own controllers, an app's api being what it is (an album and its Plumes, a collection and its packs) - only this plumbing is shared
class CardRequest
{
    public function __construct(
        private readonly CardHolder $cardHolder,
        private readonly RateLimiterGuard $rateLimiterGuard,
        // UiBundle's favorites limiter, wired in config/services.yaml as optional: a site without it calls unlimited
        private readonly ?RateLimiterFactoryInterface $limiterFactory = null,
    ) {
    }

    // Whose cards a call is about, once the limit is passed - a 429 response over it, null for a caller with neither an account nor a usable token
    public function holder(Request $request): string | Response | null
    {
        $clientIp = $request->getClientIp();
        if (null !== $clientIp && !$this->rateLimiterGuard->isAcceptedForIp($this->limiterFactory, $clientIp)) {
            return new JsonResponse(['error' => 'too_many_requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }
        $token = self::body($request)['token'] ?? null;

        return $this->cardHolder->resolve(\is_string($token) ? $token : null);
    }

    // The json body, empty when there is none or it does not parse: a call is then made as a visitor
    /** @return array<string, mixed> */
    public static function body(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (JsonException) {
            return [];
        }
    }

    // Someone's cards, never kept by a cache along the way
    public static function private(JsonResponse $response): JsonResponse
    {
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
