<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\User\UserManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for API tests. Each test runs in a DB transaction that is rolled
 * back afterwards (dama/doctrine-test-bundle), so tests don't see each other's data.
 */
abstract class ApiTestCase extends WebTestCase
{
    use ClockSensitiveTrait;

    protected const string PASSWORD = 'correct-horse-battery';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Login throttling state lives in a filesystem cache pool that would
        // otherwise leak between tests and test runs.
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    /**
     * @param list<string> $roles
     */
    protected function createUser(string $email = 'user@example.com', string $password = self::PASSWORD, array $roles = []): User
    {
        return static::getContainer()->get(UserManager::class)->create($email, $password, $roles);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string>     $headers HTTP headers, e.g. ['Authorization' => 'Bearer x']
     */
    protected function jsonRequest(string $method, string $uri, ?array $body = null, array $headers = []): Response
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $this->client->request($method, $uri, server: $server, content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));

        return $this->client->getResponse();
    }

    /**
     * @return array{token_type: string, access_token: string, expires_in: int, refresh_token: string, refresh_expires_in: int}
     */
    protected function login(string $email = 'user@example.com', string $password = self::PASSWORD): array
    {
        $response = $this->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return $this->decode($response);
    }

    protected function bearer(string $accessToken): array
    {
        return ['Authorization' => 'Bearer '.$accessToken];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    protected function assertJsonError(Response $response, int $status, ?string $error = null): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        if (null !== $error) {
            self::assertSame($error, $this->decode($response)['error'] ?? null);
        }
    }

    /**
     * Calls a protected endpoint to check whether an access token is accepted.
     * Uses logout because it's currently the only protected route; it revokes
     * the token, so call this at most once per token.
     */
    protected function accessTokenWorks(string $accessToken): bool
    {
        return Response::HTTP_NO_CONTENT === $this->jsonRequest('POST', '/api/auth/logout', headers: $this->bearer($accessToken))->getStatusCode();
    }
}
