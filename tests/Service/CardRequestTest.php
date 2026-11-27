<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Service;

use c975L\CardBundle\Service\CardHolder;
use c975L\CardBundle\Service\CardRequest;
use c975L\UiBundle\Service\RateLimiterGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CardRequestTest extends TestCase
{
    private const string TOKEN = '0123456789abcdef0123456789abcdef';

    // Over the limit, the call is answered before anyone's cards are looked at
    public function testACallOverTheLimitIsAnswered429(): void
    {
        $cardHolder = $this->createMock(CardHolder::class);
        $cardHolder->expects($this->never())->method('resolve');

        $response = new CardRequest($cardHolder, $this->guard(false))->holder($this->request(['token' => self::TOKEN]));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertSame('{"error":"too_many_requests"}', $response->getContent());
    }

    // The token is read off the json body, and the holder it resolves to is the answer
    public function testTheTokenOfTheBodyIsResolved(): void
    {
        $cardHolder = $this->createMock(CardHolder::class);
        $cardHolder->expects($this->once())->method('resolve')->with(self::TOKEN)->willReturn('u7');

        $this->assertSame('u7', new CardRequest($cardHolder, $this->guard(true))->holder($this->request(['token' => self::TOKEN])));
    }

    // A token that is no string, or a body that does not parse, is a call made as a visitor
    public function testAMalformedBodyIsAVisitor(): void
    {
        $cardHolder = $this->createMock(CardHolder::class);
        $cardHolder->expects($this->exactly(2))->method('resolve')->with(null)->willReturn(null);
        $cardRequest = new CardRequest($cardHolder, $this->guard(true));

        $this->assertNull($cardRequest->holder($this->request(['token' => ['not', 'a', 'string']])));
        $this->assertNull($cardRequest->holder(Request::create('/', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7'], content: '{not json')));
    }

    // A call with no address to count is never limited: there is no bucket to put it in
    public function testACallWithoutAnAddressIsNotLimited(): void
    {
        $guard = $this->createMock(RateLimiterGuard::class);
        $guard->expects($this->never())->method('isAcceptedForIp');
        $cardHolder = $this->createStub(CardHolder::class);
        $cardHolder->method('resolve')->willReturn(self::TOKEN);
        $request = $this->request(['token' => self::TOKEN]);
        $request->server->remove('REMOTE_ADDR');

        $this->assertSame(self::TOKEN, new CardRequest($cardHolder, $guard)->holder($request));
    }

    public function testTheBodyIsEmptyWhenItDoesNotParse(): void
    {
        $this->assertSame([], CardRequest::body(Request::create('/', 'POST', content: '')));
        $this->assertSame([], CardRequest::body(Request::create('/', 'POST', content: '{not json')));
        $this->assertSame(['token' => self::TOKEN], CardRequest::body($this->request(['token' => self::TOKEN])));
    }

    // Someone's cards are never kept by a shared cache along the way
    public function testTheAnswerIsPrivateAndNeverStored(): void
    {
        $response = CardRequest::private(new JsonResponse(['cards' => []]));

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    // A call that cannot go on without a holder answers 400 with the error the site names, the limit's 429 passed on as it is
    public function testARequiredHolderIsRefusedWithTheSiteError(): void
    {
        $cardHolder = $this->createStub(CardHolder::class);
        $cardHolder->method('resolve')->willReturn(null);
        $response = new CardRequest($cardHolder, $this->guard(true))->requiredHolder($this->request([]), 'token_required');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame(['error' => 'token_required'], json_decode((string) $response->getContent(), true));
        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, new CardRequest($cardHolder, $this->guard(false))->requiredHolder($this->request([]))->getStatusCode());
    }

    public function testARequiredHolderIsReturnedWhenResolved(): void
    {
        $cardHolder = $this->createStub(CardHolder::class);
        $cardHolder->method('resolve')->willReturn(self::TOKEN);

        $this->assertSame(self::TOKEN, new CardRequest($cardHolder, $this->guard(true))->requiredHolder($this->request(['token' => self::TOKEN])));
    }

    // A device's token alone is no account: the call is refused 403, a signed-in caller going through
    public function testAnAccountHolderNeedsASignedInCaller(): void
    {
        $device = $this->createStub(CardHolder::class);
        $device->method('resolve')->willReturn(self::TOKEN);
        $device->method('isAccount')->willReturn(false);
        $refused = new CardRequest($device, $this->guard(true))->accountHolder($this->request(['token' => self::TOKEN]));

        $this->assertInstanceOf(Response::class, $refused);
        $this->assertSame(Response::HTTP_FORBIDDEN, $refused->getStatusCode());
        $this->assertSame(['error' => 'account_required'], json_decode((string) $refused->getContent(), true));

        $account = $this->createStub(CardHolder::class);
        $account->method('resolve')->willReturn('u7');
        $account->method('isAccount')->willReturn(true);
        $this->assertSame('u7', new CardRequest($account, $this->guard(true))->accountHolder($this->request([])));
    }

    // Nobody to resolve is refused like a device, and the limit's 429 comes first
    public function testAnAccountHolderRefusesAVisitorAndPassesTheLimitOn(): void
    {
        $cardHolder = $this->createStub(CardHolder::class);
        $cardHolder->method('resolve')->willReturn(null);
        $cardHolder->method('isAccount')->willReturn(true);

        $this->assertSame(Response::HTTP_FORBIDDEN, new CardRequest($cardHolder, $this->guard(true))->accountHolder($this->request([]))->getStatusCode());
        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, new CardRequest($cardHolder, $this->guard(false))->accountHolder($this->request([]))->getStatusCode());
    }

    private function guard(bool $accepted): RateLimiterGuard
    {
        $guard = $this->createStub(RateLimiterGuard::class);
        $guard->method('isAcceptedForIp')->willReturn($accepted);

        return $guard;
    }

    /** @param array<string, mixed> $body */
    private function request(array $body): Request
    {
        return Request::create('/', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7'], content: (string) json_encode($body));
    }
}
