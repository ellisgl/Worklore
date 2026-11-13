<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Company\CompanyEntity;
use App\Company\CompanyId;
use App\Company\CompanyRepository;
use App\Identity\User;
use App\Tests\Functional\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class CompanyControllerTest extends ApiTestCase
{
    private User $user;
    /** @var array<string, string> */
    private array $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->auth = $this->bearer($this->login()['access_token']);
    }

    // ---- POST /api/companies ----------------------------------------------

    public function testCreateReturnsIdAndSavesTheCompany(): void
    {
        $response = $this->jsonRequest('POST', '/api/companies', ['name' => '  Acme Ltd ', 'overview' => 'Widgets'], $this->auth);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $id = CompanyId::fromString($this->decode($response)['id']);

        $company = $this->companies()->findOneByIdForOwner($id, $this->reloadedUser());
        self::assertNotNull($company, 'Saved, and owned by the caller');
        self::assertSame('Acme Ltd', $company->name->value, 'Name is trimmed');
        self::assertSame('Widgets', $company->overview);
    }

    public function testDuplicateNameIsAConflictIgnoringCase(): void
    {
        $this->jsonRequest('POST', '/api/companies', ['name' => 'Acme'], $this->auth);

        $response = $this->jsonRequest('POST', '/api/companies', ['name' => 'ACME'], $this->auth);

        $this->assertJsonError($response, Response::HTTP_CONFLICT, 'You already have a company named "ACME".');
        self::assertSame(1, $this->companyCount());
    }

    public function testAnotherUserCanUseTheSameName(): void
    {
        $this->createUser('other@example.com');
        $other = $this->bearer($this->login('other@example.com')['access_token']);
        $this->jsonRequest('POST', '/api/companies', ['name' => 'Acme'], $other);

        $response = $this->jsonRequest('POST', '/api/companies', ['name' => 'Acme'], $this->auth);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'missing name' => [['overview' => 'x']];
        yield 'blank name' => [['name' => '   ']];
        yield 'name too long' => [['name' => str_repeat('x', 256)]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPayloadProvider')]
    public function testInvalidPayloadIsRejectedWithViolations(array $payload): void
    {
        $response = $this->jsonRequest('POST', '/api/companies', $payload, $this->auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('name', $this->decode($response)['violations'][0]['propertyPath']);
        self::assertSame(0, $this->companyCount(), 'Nothing saved');
    }

    public function testCreateRequiresAuthentication(): void
    {
        $response = $this->jsonRequest('POST', '/api/companies', ['name' => 'Acme']);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED);
    }

    // ---- helpers ----------------------------------------------------------

    private function companyCount(): int
    {
        return $this->em()->getRepository(CompanyEntity::class)->count([]);
    }

    private function reloadedUser(): User
    {
        return $this->em()->find(User::class, $this->user->id);
    }

    private function companies(): CompanyRepository
    {
        return self::getContainer()->get(CompanyRepository::class);
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }
}
