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
        self::assertCount(1, $this->list());
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
        self::assertSame([], $this->list(), 'Nothing saved');
    }

    public function testCreateRequiresAuthentication(): void
    {
        $response = $this->jsonRequest('POST', '/api/companies', ['name' => 'Acme']);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED);
    }

    // ---- GET /api/companies -----------------------------------------------

    public function testListShowsOwnCompaniesSortedByName(): void
    {
        foreach (['Globex', 'Acme', 'Initech'] as $name) {
            $this->jsonRequest('POST', '/api/companies', ['name' => $name], $this->auth);
        }
        $this->createUser('other@example.com');
        $other = $this->bearer($this->login('other@example.com')['access_token']);
        $this->jsonRequest('POST', '/api/companies', ['name' => 'Theirs'], $other);

        self::assertSame(['Acme', 'Globex', 'Initech'], array_column($this->list(), 'name'));
    }

    public function testListUsesSnakeCaseKeys(): void
    {
        $this->jsonRequest('POST', '/api/companies', ['name' => 'Acme'], $this->auth);

        $company = $this->list()[0];

        self::assertSame(['id', 'name', 'overview', 'created_at', 'archived_at', 'product_count', 'current_position'], array_keys($company));
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DATE_RFC3339, $company['created_at']));
    }

    public function testArchivedCompaniesAreHiddenUnlessAskedFor(): void
    {
        $this->seed(static function (CompanyEntity $c): void {}, 'Acme');
        $this->seed(static fn (CompanyEntity $c) => $c->archive(new \DateTimeImmutable('2024-01-01')), 'Gone');

        self::assertSame(['Acme'], array_column($this->list(), 'name'));

        $all = $this->list('?include_archived=1');
        self::assertSame(['Acme', 'Gone'], array_column($all, 'name'));
        self::assertNull($all[0]['archived_at']);
        self::assertNotNull($all[1]['archived_at']);
    }

    public function testListCountsActiveProductsAndShowsCurrentPosition(): void
    {
        $this->seed(static function (CompanyEntity $c): void {
            $c->addProduct('Widget');
            $c->addProduct('Gadget');
            $c->addProduct('Retired')->archive(new \DateTimeImmutable());
            $c->addPosition('Junior', new \DateTimeImmutable('2019-01-01'))->end(new \DateTimeImmutable('2021-12-31'));
            $c->addPosition('Senior', new \DateTimeImmutable('2022-01-01'));
        }, 'Acme');
        $this->seed(static function (CompanyEntity $c): void {}, 'Globex');

        [$acme, $globex] = $this->list();

        self::assertSame(2, $acme['product_count']);
        self::assertSame('Senior', $acme['current_position']);
        self::assertSame(0, $globex['product_count']);
        self::assertNull($globex['current_position']);
    }

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/companies');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    // ---- helpers ----------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function list(string $query = ''): array
    {
        $response = $this->jsonRequest('GET', '/api/companies'.$query, headers: $this->auth);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return $this->decode($response);
    }

    /**
     * Saves a company for the test user directly, for state the API can't
     * create yet (products, positions, archiving).
     *
     * @param \Closure(CompanyEntity): mixed $build
     */
    private function seed(\Closure $build, string $name): void
    {
        $em = $this->em();
        $company = new CompanyEntity($this->reloadedUser(), $name);
        $build($company);
        $em->persist($company);
        $em->flush();
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
