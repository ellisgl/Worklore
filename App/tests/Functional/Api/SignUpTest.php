<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Identity\UserRepository;
use App\Tests\Functional\ApiTestCase;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

use function str_repeat;

/**
 * POST /api/auth/signup, which APP_SIGNUP_ENABLED turns on (off in .env).
 */
final class SignUpTest extends ApiTestCase
{
    private const string NEW_PASSWORD = 'staple-battery-horse';

    protected function tearDown(): void
    {
        self::setSignUp(null);
        parent::tearDown();
    }

    /**
     * @throws JsonException
     */
    public function testOffByDefault(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/signup', ['email' => 'new@example.com', 'password' => self::NEW_PASSWORD]);

        $this->assertJsonError($response, Response::HTTP_FORBIDDEN, 'Sign-up is disabled.');
        self::assertNull(self::getContainer()->get(UserRepository::class)->findOneByEmail('new@example.com'));
    }

    /**
     * @throws JsonException
     */
    public function testCreatesAnAccountAndLogsIn(): void
    {
        self::setSignUp(true);

        $response = $this->jsonRequest('POST', '/api/auth/signup', ['email' => ' New@Example.com ', 'password' => self::NEW_PASSWORD]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $tokens = $this->tokens($response);
        self::assertSame('Bearer', $tokens['token_type']);
        self::assertSame('new@example.com', $this->getJson('/api/me', $this->bearer($tokens['access_token']))['email'], 'Email normalized');
        $this->login('new@example.com', self::NEW_PASSWORD);
    }

    /**
     * @throws JsonException
     */
    public function testAnExistingEmailIsAConflict(): void
    {
        self::setSignUp(true);
        $this->createUser('taken@example.com');

        $response = $this->jsonRequest('POST', '/api/auth/signup', ['email' => 'TAKEN@example.com', 'password' => self::NEW_PASSWORD]);

        $this->assertJsonError($response, Response::HTTP_CONFLICT, 'An account with this email already exists.');
        $this->login('taken@example.com');  // the original password still works
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'missing email' => [['password' => self::NEW_PASSWORD], 'email'];
        yield 'not an email' => [['email' => 'nope', 'password' => self::NEW_PASSWORD], 'email'];
        yield 'email too long' => [['email' => str_repeat('x', 175).'@example.com', 'password' => self::NEW_PASSWORD], 'email'];
        yield 'missing password' => [['email' => 'new@example.com'], 'password'];
        yield 'password too short' => [['email' => 'new@example.com', 'password' => 'short'], 'password'];
        yield 'password too long' => [['email' => 'new@example.com', 'password' => str_repeat('x', 4097)], 'password'];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws JsonException
     */
    #[DataProvider('invalidProvider')]
    public function testInvalidSignUpIsRejected(array $payload, string $field): void
    {
        self::setSignUp(true);

        $response = $this->jsonRequest('POST', '/api/auth/signup', $payload);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($field, $this->firstViolationPath($response));
    }

    /**
     * @throws JsonException
     */
    public function testSignUpsPerAddressAreLimited(): void
    {
        self::setSignUp(true);
        for ($i = 1; $i <= 5; ++$i) {
            $this->createdOk($this->jsonRequest('POST', '/api/auth/signup', ['email' => "user$i@example.com", 'password' => self::NEW_PASSWORD]));
        }

        $response = $this->jsonRequest('POST', '/api/auth/signup', ['email' => 'user6@example.com', 'password' => self::NEW_PASSWORD]);

        $this->assertJsonError($response, Response::HTTP_TOO_MANY_REQUESTS, 'Too many sign-ups from this address; try again later.');
        self::assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        self::assertNull(self::getContainer()->get(UserRepository::class)->findOneByEmail('user6@example.com'));
    }

    private function createdOk(Response $response): void
    {
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * Sets APP_SIGNUP_ENABLED for the next request's kernel; null restores
     * the default from .env.
     */
    private static function setSignUp(?bool $enabled): void
    {
        if (null === $enabled) {
            unset($_ENV['APP_SIGNUP_ENABLED'], $_SERVER['APP_SIGNUP_ENABLED']);

            return;
        }
        $_ENV['APP_SIGNUP_ENABLED'] = $_SERVER['APP_SIGNUP_ENABLED'] = $enabled ? '1' : '0';
    }
}
