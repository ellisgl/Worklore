<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
use App\Repository\ApiTokenRepository;
use App\Tests\Functional\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class AuthControllerTest extends ApiTestCase
{
    private const int ACCESS_TTL = 900;
    private const int REFRESH_TTL = 2592000;

    // ---- login ------------------------------------------------------------

    public function testLoginReturnsTokenPair(): void
    {
        $this->createUser();

        $tokens = $this->login();

        self::assertSame('Bearer', $tokens['token_type']);
        self::assertStringStartsWith('wla_', $tokens['access_token']);
        self::assertStringStartsWith('wlr_', $tokens['refresh_token']);
        self::assertSame(self::ACCESS_TTL, $tokens['expires_in']);
        self::assertSame(self::REFRESH_TTL, $tokens['refresh_expires_in']);
    }

    public function testTokensAreStoredOnlyAsHashes(): void
    {
        $this->createUser();
        $tokens = $this->login();

        $row = self::getContainer()->get(ApiTokenRepository::class)
            ->findOneByAccessTokenHash(hash('sha256', $tokens['access_token']));

        self::assertNotNull($row);
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $raw = $connection->fetchAssociative('SELECT access_token_hash, refresh_token_hash FROM api_token WHERE id = ?', [$row->getId()->toRfc4122()]);
        self::assertNotContains($tokens['access_token'], $raw);
        self::assertNotContains($tokens['refresh_token'], $raw);
    }

    public function testLoginEmailIsCaseInsensitive(): void
    {
        $this->createUser('user@example.com');

        $this->login('  User@Example.COM ');

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badCredentialsProvider(): iterable
    {
        yield 'wrong password' => ['user@example.com', 'wrong-password-123'];
        yield 'unknown email' => ['nobody@example.com', self::PASSWORD];
    }

    #[DataProvider('badCredentialsProvider')]
    public function testLoginWithBadCredentialsIsRejected(string $email, string $password): void
    {
        $this->createUser();

        $response = $this->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED, 'Invalid credentials.');
    }

    public function testLoginWithMissingFieldsIsRejected(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/login', ['email' => 'user@example.com']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testLoginIsThrottledAfterRepeatedFailures(): void
    {
        $this->createUser();

        for ($i = 0; $i < 6; ++$i) {
            $this->jsonRequest('POST', '/api/auth/login', ['email' => 'user@example.com', 'password' => 'wrong-password-123']);
        }

        // Even the correct password is refused while throttled.
        $response = $this->jsonRequest('POST', '/api/auth/login', ['email' => 'user@example.com', 'password' => self::PASSWORD]);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED);
        self::assertStringContainsString('Too many failed login attempts', $this->decode($response)['error']);
    }

    // ---- access tokens / protected routes ---------------------------------

    public function testProtectedRouteWithoutTokenReturns401(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/logout');

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
    }

    public function testProtectedRouteWithInvalidTokenReturns401(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/logout', headers: $this->bearer('wla_not-a-real-token'));

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED, 'Invalid or expired access token.');
        self::assertSame('Bearer error="invalid_token"', $response->headers->get('WWW-Authenticate'));
    }

    public function testRefreshTokenCannotBeUsedAsAccessToken(): void
    {
        $this->createUser();
        $tokens = $this->login();

        self::assertFalse($this->accessTokenWorks($tokens['refresh_token']));
    }

    public function testAccessTokenExpires(): void
    {
        $this->createUser();
        $clock = self::mockTime();
        $tokens = $this->login();

        $clock->sleep(self::ACCESS_TTL + 1);

        $response = $this->jsonRequest('POST', '/api/auth/logout', headers: $this->bearer($tokens['access_token']));
        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED, 'Invalid or expired access token.');
    }

    public function testAccessTokenIsValidJustBeforeExpiry(): void
    {
        $this->createUser();
        $clock = self::mockTime();
        $tokens = $this->login();

        $clock->sleep(self::ACCESS_TTL - 1);

        self::assertTrue($this->accessTokenWorks($tokens['access_token']));
    }

    // ---- refresh ----------------------------------------------------------

    public function testRefreshIssuesNewPairAndInvalidatesOldOne(): void
    {
        $this->createUser();
        $old = $this->login();

        $response = $this->jsonRequest('POST', '/api/auth/refresh', ['refresh_token' => $old['refresh_token']]);

        self::assertResponseIsSuccessful();
        $new = $this->decode($response);
        self::assertNotSame($old['access_token'], $new['access_token']);
        self::assertNotSame($old['refresh_token'], $new['refresh_token']);
        self::assertSame(self::ACCESS_TTL, $new['expires_in']);

        self::assertFalse($this->accessTokenWorks($old['access_token']), 'Old access token must stop working');
        self::assertTrue($this->accessTokenWorks($new['access_token']), 'New access token must work');
    }

    public function testRefreshTokenCannotBeReused(): void
    {
        $this->createUser();
        $tokens = $this->login();
        $this->jsonRequest('POST', '/api/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);
        self::assertResponseIsSuccessful();

        $response = $this->jsonRequest('POST', '/api/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED, 'Invalid or expired refresh token.');
    }

    public function testRefreshWorksAfterAccessTokenExpired(): void
    {
        $this->createUser();
        $clock = self::mockTime();
        $tokens = $this->login();
        $clock->sleep(self::ACCESS_TTL + 60);

        $this->jsonRequest('POST', '/api/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);

        self::assertResponseIsSuccessful();
    }

    public function testExpiredRefreshTokenIsRejected(): void
    {
        $this->createUser();
        $clock = self::mockTime();
        $tokens = $this->login();
        $clock->sleep(self::REFRESH_TTL + 1);

        $response = $this->jsonRequest('POST', '/api/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED, 'Invalid or expired refresh token.');
    }

    public function testRefreshWithUnknownTokenIsRejected(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/refresh', ['refresh_token' => 'wlr_bogus']);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED, 'Invalid or expired refresh token.');
    }

    public function testAccessTokenCannotBeUsedAsRefreshToken(): void
    {
        $this->createUser();
        $tokens = $this->login();

        $response = $this->jsonRequest('POST', '/api/auth/refresh', ['refresh_token' => $tokens['access_token']]);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED);
    }

    public function testRefreshWithoutTokenFailsValidation(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/refresh', []);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $violations = $this->decode($response)['violations'];
        self::assertSame('refresh_token', $violations[0]['propertyPath']);
    }

    // ---- logout -----------------------------------------------------------

    public function testLogoutRevokesAccessAndRefreshToken(): void
    {
        $this->createUser();
        $tokens = $this->login();

        $response = $this->jsonRequest('POST', '/api/auth/logout', headers: $this->bearer($tokens['access_token']));
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        self::assertFalse($this->accessTokenWorks($tokens['access_token']));
        $response = $this->jsonRequest('POST', '/api/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);
        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED);
    }

    public function testLogoutOnlyEndsCurrentSession(): void
    {
        $this->createUser();
        $phone = $this->login();
        $laptop = $this->login();

        self::assertTrue($this->accessTokenWorks($phone['access_token']));

        $response = $this->jsonRequest('POST', '/api/auth/refresh', ['refresh_token' => $laptop['refresh_token']]);
        self::assertResponseIsSuccessful('Other sessions must survive a logout');
    }

    // ---- archived users ---------------------------------------------------

    public function testArchivedUserCannotLogIn(): void
    {
        $this->archive($this->createUser());

        $response = $this->jsonRequest('POST', '/api/auth/login', ['email' => 'user@example.com', 'password' => self::PASSWORD]);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED, 'Account is archived.');
    }

    public function testArchivedUserWithWrongPasswordGetsGenericError(): void
    {
        $this->archive($this->createUser());

        $response = $this->jsonRequest('POST', '/api/auth/login', ['email' => 'user@example.com', 'password' => 'wrong-password-123']);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED, 'Invalid credentials.');
    }

    public function testArchivedUsersExistingAccessTokenStopsWorking(): void
    {
        $user = $this->createUser();
        $tokens = $this->login();

        // Archive on the entity directly, so the session isn't revoked: the
        // user checker alone must reject it.
        $this->archive($user);

        self::assertFalse($this->accessTokenWorks($tokens['access_token']));
    }

    public function testArchivedUserCannotRefresh(): void
    {
        $user = $this->createUser();
        $tokens = $this->login();
        $this->archive($user);

        $response = $this->jsonRequest('POST', '/api/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED, 'Invalid or expired refresh token.');
    }

    public function testUnarchivedUserCanLogInAgain(): void
    {
        $user = $this->createUser();
        $this->archive($user);
        $user->unarchive();
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->login();

        self::assertResponseIsSuccessful();
    }

    // ---- general API behaviour --------------------------------------------

    public function testUnknownApiRouteReturnsJson404(): void
    {
        $this->client->request('GET', '/api/does-not-exist');

        $response = $this->client->getResponse();
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(404, $this->decode($response)['status']);
    }

    public function testLoginRouteOnlyAcceptsPost(): void
    {
        $this->client->request('GET', '/api/auth/login');

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    private function archive(User $user): void
    {
        $user->archive(new \DateTimeImmutable());
        self::getContainer()->get('doctrine')->getManager()->flush();
    }
}
