<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Identity\Application\ArchiveUser;
use App\Shared\CommandBus;
use App\Tests\Functional\ApiTestCase;
use JsonException;
use Monolog\Handler\TestHandler;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function array_column;
use function array_keys;
use function array_map;
use function substr;

final class ImpersonationTest extends ApiTestCase
{
    /** @var array<string, string> */
    private array $admin;
    private string $adminId;
    private string $userId;

    /**
     * @throws JsonException
     */
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime('2026-03-04 05:06:07 UTC');
        $this->createUser('admin@example.com', roles: ['ROLE_ADMIN']);
        $this->admin = $this->bearer($this->login('admin@example.com')['access_token']);
        $this->adminId = $this->id($this->admin);
        $this->userId = $this->id($this->signIn());
    }

    /**
     * @throws JsonException
     */
    public function testAnAdminActsAsTheUserAndIsRecordedDoingSo(): void
    {
        $response = $this->jsonRequest('POST', '/api/admin/users/'.$this->userId.'/impersonate', headers: $this->admin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $token = $this->decode($response);
        self::assertSame(['token_type', 'access_token', 'expires_in', 'user_id', 'user_email'], array_keys($token));
        self::assertSame([900, $this->userId, 'user@example.com'], [$token['expires_in'], $token['user_id'], $token['user_email']]);
        self::assertTrue($this->hasLogged('Impersonation started', ['impersonator_id' => $this->adminId, 'user_id' => $this->userId]));

        $accessToken = $token['access_token'] ?? null;
        self::assertIsString($accessToken);
        $as = $this->bearer($accessToken);
        $me = $this->getJson('/api/me', $as);
        self::assertSame([$this->userId, $this->adminId], [$me['id'], $me['impersonated_by']]);

        $company = $this->createdId('/api/companies', ['name' => 'Acme'], $as);
        self::assertTrue($this->hasLogged('company.created', ['event' => 'company.created', 'company_id' => $company, 'owner_id' => $this->userId, 'impersonator_id' => $this->adminId]));
        $user = $this->bearer($this->login()['access_token']);
        $this->jsonRequest('PATCH', '/api/companies/'.$company, ['name' => 'Acme Inc'], $user);

        self::assertNull($this->getJson('/api/me', $user)['impersonated_by'], 'The user\'s own token is not an impersonation');
        $history = $this->getItems('/api/companies/'.$company.'/history', $user);
        self::assertSame([$this->adminId, null], array_column($history, 'impersonated_by'), 'The user can see what the admin did');
    }

    /**
     * @throws JsonException
     */
    public function testSomeThingsAreNotAllowedWhileImpersonating(): void
    {
        $as = $this->impersonate($this->userId);

        foreach ([
            ['POST', '/api/me/password', ['current_password' => self::PASSWORD, 'new_password' => 'something-else-123']],
            ['POST', '/api/me/sessions/revoke', null],
            ['GET', '/api/me/export', null],
            ['GET', '/api/me/export/archive', null],
            ['POST', '/api/me/import', ['format' => 'worklore-export']],
        ] as [$method, $uri, $body]) {
            $this->assertJsonError($this->jsonRequest($method, $uri, $body, $as), Response::HTTP_FORBIDDEN, 'Not allowed while impersonating a user.');
        }
        self::assertGreaterThan(0, $this->login()['expires_in'], 'The password is unchanged');
    }

    /**
     * @throws JsonException
     */
    public function testTheTokenExpiresAndCannotBeRefreshedOrReused(): void
    {
        $as = $this->impersonate($this->userId);
        self::assertTrue($this->accessTokenWorks(substr($as['Authorization'], 7)));

        $this->assertJsonError($this->jsonRequest('POST', '/api/admin/users', headers: $as), Response::HTTP_METHOD_NOT_ALLOWED);
        $this->assertJsonError($this->jsonRequest('GET', '/api/admin/users', headers: $as), Response::HTTP_FORBIDDEN);

        self::mockTime('2026-03-04 05:21:08 UTC');
        $this->assertJsonError($this->jsonRequest('GET', '/api/me', headers: $as), Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @throws JsonException
     */
    public function testLoggingOutEndsTheImpersonation(): void
    {
        $as = $this->impersonate($this->userId);

        self::assertSame(Response::HTTP_NO_CONTENT, $this->jsonRequest('POST', '/api/auth/logout', headers: $as)->getStatusCode());

        $this->assertJsonError($this->jsonRequest('GET', '/api/me', headers: $as), Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @throws JsonException
     * @throws Throwable
     */
    public function testWhoCanBeImpersonated(): void
    {
        $this->createUser('other-admin@example.com', roles: ['ROLE_ADMIN']);
        $otherAdmin = $this->id($this->bearer($this->login('other-admin@example.com')['access_token']));
        $archived = $this->createUser('archived@example.com');
        self::getContainer()->get(CommandBus::class)->dispatch(new ArchiveUser($archived->id));

        $cases = [
            [$this->adminId, Response::HTTP_UNPROCESSABLE_ENTITY, 'You can\'t impersonate yourself.'],
            [$otherAdmin, Response::HTTP_UNPROCESSABLE_ENTITY, 'Admins can\'t be impersonated.'],
            [$archived->id->toRfc4122(), Response::HTTP_UNPROCESSABLE_ENTITY, 'Archived users can\'t be impersonated; unarchive them first.'],
            ['01a1da4c-fb84-7d3a-9e81-18d96905f2f5', Response::HTTP_NOT_FOUND, 'User not found.'],
        ];
        foreach ($cases as [$id, $status, $error]) {
            $this->assertJsonError($this->jsonRequest('POST', '/api/admin/users/'.$id.'/impersonate', headers: $this->admin), $status, $error);
        }

        $user = $this->bearer($this->login()['access_token']);
        $this->assertJsonError($this->jsonRequest('POST', '/api/admin/users/'.$otherAdmin.'/impersonate', headers: $user), Response::HTTP_FORBIDDEN);
    }

    /**
     * @throws JsonException
     */
    public function testAdminsCanFindUsers(): void
    {
        $users = $this->getItems('/api/admin/users?email=USER@', $this->admin);

        self::assertSame([['id' => $this->userId, 'email' => 'user@example.com', 'admin' => false]], array_map(
            static fn (array $user): array => ['id' => $user['id'], 'email' => $user['email'], 'admin' => $user['admin']],
            $users,
        ));
        self::assertCount(2, $this->getItems('/api/admin/users', $this->admin));
    }

    /**
     * @return array<string, string>
     *
     * @throws JsonException
     */
    private function impersonate(string $userId): array
    {
        $response = $this->jsonRequest('POST', '/api/admin/users/'.$userId.'/impersonate', headers: $this->admin);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return $this->bearer($this->field($response, 'access_token'));
    }

    /**
     * @param array<string, string> $auth
     *
     * @throws JsonException
     */
    private function id(array $auth): string
    {
        $id = $this->getJson('/api/me', $auth)['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    /**
     * Whether the last request logged $message on the domain channel with
     * exactly this context.
     *
     * @param array<string, string> $context
     */
    private function hasLogged(string $message, array $context): bool
    {
        $log = self::getContainer()->get('monolog.handler.domain'); // @phpstan-ignore symfonyContainer.serviceNotFound
        self::assertInstanceOf(TestHandler::class, $log);
        foreach ($log->getRecords() as $record) {
            if ($message === $record->message && $context === $record->context) {
                return true;
            }
        }

        return false;
    }
}
