<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

use function array_keys;
use function str_repeat;

final class AccountControllerTest extends ApiTestCase
{
    private const string NEW_PASSWORD = 'staple-battery-horse';

    /**
     * @throws JsonException
     */
    public function testShowsTheSignedInAccount(): void
    {
        self::mockTime('2026-03-04 05:06:07 UTC');
        $user = $this->createUser('Me@Example.com');

        $account = $this->getJson('/api/me', $this->bearer($this->login('me@example.com')['access_token']));

        self::assertSame(['id', 'email', 'created_at'], array_keys($account));
        self::assertSame($user->id->toRfc4122(), $account['id']);
        self::assertSame('me@example.com', $account['email']);
        self::assertSame('2026-03-04T05:06:07+00:00', $account['created_at']);
    }

    /**
     * @throws JsonException
     */
    public function testChangingThePasswordEndsEverySessionAndReturnsANewOne(): void
    {
        $this->createUser();
        $first = $this->login();
        $second = $this->login();

        $response = $this->jsonRequest('POST', '/api/me/password', ['current_password' => self::PASSWORD, 'new_password' => self::NEW_PASSWORD], $this->bearer($first['access_token']));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $tokens = $this->tokens($response);
        self::assertFalse($this->accessTokenWorks($first['access_token']), 'The session that made the change ends too');
        self::assertFalse($this->accessTokenWorks($second['access_token']), 'Every other session ends');
        self::assertTrue($this->accessTokenWorks($tokens['access_token']), 'The returned pair works');

        $this->assertJsonError($this->jsonRequest('POST', '/api/auth/login', ['email' => 'user@example.com', 'password' => self::PASSWORD]), Response::HTTP_UNAUTHORIZED);
        $this->login(password: self::NEW_PASSWORD);
    }

    /**
     * @throws JsonException
     */
    public function testWrongCurrentPasswordChangesNothing(): void
    {
        $auth = $this->signIn();

        $response = $this->jsonRequest('POST', '/api/me/password', ['current_password' => 'not-my-password', 'new_password' => self::NEW_PASSWORD], $auth);

        $this->assertJsonError($response, Response::HTTP_UNPROCESSABLE_ENTITY, 'Current password is incorrect.');
        self::assertSame(Response::HTTP_OK, $this->jsonRequest('GET', '/api/me', headers: $auth)->getStatusCode(), 'Still signed in');
        $this->login();
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidChangeProvider(): iterable
    {
        yield 'missing current password' => [['new_password' => self::NEW_PASSWORD], 'current_password'];
        yield 'missing new password' => [['current_password' => self::PASSWORD], 'new_password'];
        yield 'new password too short' => [['current_password' => self::PASSWORD, 'new_password' => 'short'], 'new_password'];
        yield 'new password too long' => [['current_password' => self::PASSWORD, 'new_password' => str_repeat('x', 4097)], 'new_password'];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws JsonException
     */
    #[DataProvider('invalidChangeProvider')]
    public function testInvalidChangeIsRejected(array $payload, string $field): void
    {
        $auth = $this->signIn();

        $response = $this->jsonRequest('POST', '/api/me/password', $payload, $auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($field, $this->firstViolationPath($response));
        $this->login();
    }

    /**
     * @throws JsonException
     */
    public function testLogOutEverywhere(): void
    {
        $this->createUser();
        $laptop = $this->login();
        $phone = $this->login();

        $response = $this->jsonRequest('POST', '/api/me/sessions/revoke', headers: $this->bearer($laptop['access_token']));

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertFalse($this->accessTokenWorks($laptop['access_token']), 'This session ends too');
        self::assertFalse($this->accessTokenWorks($phone['access_token']));
        foreach ([$laptop, $phone] as $tokens) {
            $refresh = $this->jsonRequest('POST', '/api/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);
            $this->assertJsonError($refresh, Response::HTTP_UNAUTHORIZED);
        }
        $this->login();
    }

    /**
     * @throws JsonException
     */
    public function testAccountRequiresAuthentication(): void
    {
        $this->assertJsonError($this->jsonRequest('GET', '/api/me'), Response::HTTP_UNAUTHORIZED);
        $this->assertJsonError($this->jsonRequest('POST', '/api/me/sessions/revoke'), Response::HTTP_UNAUTHORIZED);
        $this->assertJsonError($this->jsonRequest('POST', '/api/me/password', ['current_password' => 'x', 'new_password' => self::NEW_PASSWORD]), Response::HTTP_UNAUTHORIZED);
    }
}
