<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Company\CompanyEntity;
use App\Company\CompanyId;
use App\Company\CompanyRepository;
use App\Identity\User;
use App\Tests\Functional\ApiTestCase;
use App\Tests\Functional\FindsEntities;
use Closure;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

use const DATE_RFC3339;

final class CompanyControllerTest extends ApiTestCase
{
    use FindsEntities;

    private User $user;
    /** @var array<string, string> */
    private array $auth;

    /**
     * @throws JsonException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->auth = $this->bearer($this->login()['access_token']);
    }

    // ---- POST /api/companies ----------------------------------------------

    /**
     * @throws JsonException
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testCreateReturnsIdAndSavesTheCompany(): void
    {
        $response = $this->jsonRequest('POST', '/api/companies', ['name' => '  Acme Ltd ', 'overview' => 'Widgets'], $this->auth);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $id = CompanyId::fromString($this->field($response, 'id'));

        $company = $this->companies()->findOneByIdForOwner($id, $this->reloadedUser());
        self::assertNotNull($company, 'Saved, and owned by the caller');
        self::assertSame('Acme Ltd', $company->name->value, 'Name is trimmed');
        self::assertSame('Widgets', $company->overview);
    }

    /**
     * @throws JsonException
     */
    public function testDuplicateNameIsAConflictIgnoringCase(): void
    {
        $this->jsonRequest('POST', '/api/companies', ['name' => 'Acme'], $this->auth);

        $response = $this->jsonRequest('POST', '/api/companies', ['name' => 'ACME'], $this->auth);

        $this->assertJsonError($response, Response::HTTP_CONFLICT, 'You already have a company named "ACME".');
        self::assertCount(1, $this->list());
    }

    /**
     * @throws JsonException
     */
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

    /**
     * @param array<string, mixed> $payload
     *
     * @throws JsonException
     */
    #[DataProvider('invalidPayloadProvider')]
    public function testInvalidPayloadIsRejectedWithViolations(array $payload): void
    {
        $response = $this->jsonRequest('POST', '/api/companies', $payload, $this->auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('name', $this->firstViolationPath($response));
        self::assertSame([], $this->list(), 'Nothing saved');
    }

    /**
     * @throws JsonException
     */
    public function testCreateRequiresAuthentication(): void
    {
        $response = $this->jsonRequest('POST', '/api/companies', ['name' => 'Acme']);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED);
    }

    // ---- GET /api/companies -----------------------------------------------

    /**
     * @throws JsonException
     */
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

    /**
     * @throws JsonException
     */
    public function testListUsesSnakeCaseKeys(): void
    {
        $this->jsonRequest('POST', '/api/companies', ['name' => 'Acme'], $this->auth);

        $company = $this->list()[0];

        self::assertSame(['id', 'name', 'overview', 'created_at', 'archived_at', 'product_count', 'current_position'], array_keys($company));
        self::assertIsString($company['created_at']);
        self::assertNotFalse(DateTimeImmutable::createFromFormat(DATE_RFC3339, $company['created_at']));
    }

    /**
     * @throws DateMalformedStringException
     * @throws JsonException
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testArchivedCompaniesAreHiddenUnlessAskedFor(): void
    {
        $this->seed(static function (CompanyEntity $c): void {}, 'Acme');
        $this->seed(static fn (CompanyEntity $c) => $c->archive(new DateTimeImmutable('2024-01-01')), 'Gone');

        self::assertSame(['Acme'], array_column($this->list(), 'name'));

        $all = $this->list('?include_archived=1');
        self::assertSame(['Acme', 'Gone'], array_column($all, 'name'));
        self::assertNull($all[0]['archived_at']);
        self::assertNotNull($all[1]['archived_at']);
    }

    /**
     * @throws DateMalformedStringException
     * @throws JsonException
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testListCountsActiveProductsAndShowsCurrentPosition(): void
    {
        $this->seed(static function (CompanyEntity $c): void {
            $c->addProduct('Widget');
            $c->addProduct('Gadget');
            $c->addProduct('Retired')->archive(new DateTimeImmutable());
            $c->addPosition('Junior', new DateTimeImmutable('2019-01-01'))->end(new DateTimeImmutable('2021-12-31'));
            $c->addPosition('Senior', new DateTimeImmutable('2022-01-01'));
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

    // ---- GET /api/companies/{id} ------------------------------------------

    /**
     * @throws JsonException
     */
    public function testShowReturnsTheCompany(): void
    {
        $created = $this->jsonRequest('POST', '/api/companies', ['name' => 'Acme', 'overview' => 'Widgets'], $this->auth);
        $id = $this->field($created, 'id');

        $response = $this->jsonRequest('GET', '/api/companies/'.$id, headers: $this->auth);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $company = $this->decode($response);
        self::assertSame($id, $company['id']);
        self::assertSame('Acme', $company['name']);
        self::assertSame('Widgets', $company['overview']);
        self::assertSame($this->list()[0], $company, 'Same fields as in the list');
    }

    /**
     * @throws DateMalformedStringException
     * @throws JsonException
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testShowIncludesArchivedCompanies(): void
    {
        $gone = $this->seed(static fn (CompanyEntity $c) => $c->archive(new DateTimeImmutable('2024-01-01')), 'Gone');

        $response = $this->jsonRequest('GET', '/api/companies/'.$gone->id, headers: $this->auth);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertNotNull($this->decode($response)['archived_at']);
    }

    /**
     * @throws JsonException
     */
    public function testAnotherUsersCompanyIsNotFound(): void
    {
        $this->createUser('other@example.com');
        $other = $this->bearer($this->login('other@example.com')['access_token']);
        $theirs = $this->field($this->jsonRequest('POST', '/api/companies', ['name' => 'Theirs'], $other), 'id');

        $response = $this->jsonRequest('GET', '/api/companies/'.$theirs, headers: $this->auth);

        $this->assertJsonError($response, Response::HTTP_NOT_FOUND, 'Company not found.');
    }

    /**
     * @throws JsonException
     */
    public function testUnknownCompanyIsNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/companies/'.CompanyId::generate(), headers: $this->auth);

        $this->assertJsonError($response, Response::HTTP_NOT_FOUND, 'Company not found.');
    }

    /**
     * @throws JsonException
     */
    public function testMalformedIdIsNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/companies/not-a-uuid', headers: $this->auth);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testShowRequiresAuthentication(): void
    {
        $response = $this->jsonRequest('GET', '/api/companies/'.CompanyId::generate());

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED);
    }

    // ---- PATCH /api/companies/{id} ----------------------------------------

    /**
     * @throws JsonException
     */
    public function testEditChangesOnlyTheFieldsSent(): void
    {
        $id = $this->create('Acme', 'Widgets');

        $response = $this->jsonRequest('PATCH', '/api/companies/'.$id, ['name' => ' Acme Ltd '], $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $company = $this->show($id);
        self::assertSame('Acme Ltd', $company['name'], 'Renamed and trimmed');
        self::assertSame('Widgets', $company['overview'], 'Overview not sent, so unchanged');
    }

    /**
     * @throws JsonException
     */
    public function testEditOverviewAndClearIt(): void
    {
        $id = $this->create('Acme');

        $this->jsonRequest('PATCH', '/api/companies/'.$id, ['overview' => 'Widgets'], $this->auth);
        self::assertSame('Widgets', $this->show($id)['overview']);
        self::assertSame('Acme', $this->show($id)['name'], 'Name not sent, so unchanged');

        $this->jsonRequest('PATCH', '/api/companies/'.$id, ['overview' => ''], $this->auth);
        self::assertNull($this->show($id)['overview'], 'An empty overview clears it');
    }

    /**
     * @throws JsonException
     */
    public function testEditWithNothingToChangeIsANoOp(): void
    {
        $id = $this->create('Acme', 'Widgets');

        $response = $this->jsonRequest('PATCH', '/api/companies/'.$id, ['name' => null], $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('Acme', $this->show($id)['name']);
    }

    /**
     * @throws JsonException
     */
    public function testRenameOntoAnotherCompanyIsAConflict(): void
    {
        $this->create('Globex');
        $id = $this->create('Acme', 'Widgets');

        $response = $this->jsonRequest('PATCH', '/api/companies/'.$id, ['name' => 'GLOBEX', 'overview' => 'Changed'], $this->auth);

        $this->assertJsonError($response, Response::HTTP_CONFLICT, 'You already have a company named "GLOBEX".');
        self::assertSame('Widgets', $this->show($id)['overview'], 'Nothing applied: the edit is all or nothing');
    }

    /**
     * @throws JsonException
     */
    public function testRenameToADifferentCaseOfItsOwnName(): void
    {
        $id = $this->create('acme');

        $response = $this->jsonRequest('PATCH', '/api/companies/'.$id, ['name' => 'ACME'], $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('ACME', $this->show($id)['name']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidEditProvider(): iterable
    {
        yield 'blank name' => [['name' => '   ']];
        yield 'name too long' => [['name' => str_repeat('x', 256)]];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws JsonException
     */
    #[DataProvider('invalidEditProvider')]
    public function testInvalidEditIsRejected(array $payload): void
    {
        $id = $this->create('Acme');

        $response = $this->jsonRequest('PATCH', '/api/companies/'.$id, $payload, $this->auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('name', $this->firstViolationPath($response));
        self::assertSame('Acme', $this->show($id)['name']);
    }

    /**
     * @throws JsonException
     */
    public function testEditingAnotherUsersCompanyIsNotFound(): void
    {
        $this->createUser('other@example.com');
        $other = $this->bearer($this->login('other@example.com')['access_token']);
        $theirs = $this->field($this->jsonRequest('POST', '/api/companies', ['name' => 'Theirs'], $other), 'id');

        $response = $this->jsonRequest('PATCH', '/api/companies/'.$theirs, ['name' => 'Mine now'], $this->auth);

        $this->assertJsonError($response, Response::HTTP_NOT_FOUND, 'Company not found.');
        $check = $this->jsonRequest('GET', '/api/companies/'.$theirs, headers: $other);
        self::assertSame('Theirs', $this->field($check, 'name'), 'Unchanged');
    }

    /**
     * @throws JsonException
     */
    public function testEditRequiresAuthentication(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/companies/'.CompanyId::generate(), ['name' => 'Acme']);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED);
    }

    // ---- POST /api/companies/{id}/archive, /unarchive ---------------------

    /**
     * @throws JsonException
     */
    public function testArchiveHidesTheCompanyFromTheList(): void
    {
        self::mockTime('2026-03-04 05:06:07 UTC');
        $id = $this->create('Acme');
        $this->create('Globex');

        $response = $this->jsonRequest('POST', '/api/companies/'.$id.'/archive', headers: $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['Globex'], array_column($this->list(), 'name'));
        self::assertSame('2026-03-04T05:06:07+00:00', $this->show($id)['archived_at'], 'Still readable by id, with the clock\'s time');
    }

    /**
     * @throws JsonException
     */
    public function testArchivingTwiceKeepsTheOriginalTime(): void
    {
        $clock = self::mockTime('2026-03-04 05:06:07 UTC');
        $id = $this->create('Acme');
        $this->jsonRequest('POST', '/api/companies/'.$id.'/archive', headers: $this->auth);
        $clock->sleep(3600);

        $response = $this->jsonRequest('POST', '/api/companies/'.$id.'/archive', headers: $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('2026-03-04T05:06:07+00:00', $this->show($id)['archived_at']);
    }

    /**
     * @throws JsonException
     */
    public function testUnarchiveBringsTheCompanyBack(): void
    {
        $id = $this->create('Acme');
        $this->jsonRequest('POST', '/api/companies/'.$id.'/archive', headers: $this->auth);

        $response = $this->jsonRequest('POST', '/api/companies/'.$id.'/unarchive', headers: $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame(['Acme'], array_column($this->list(), 'name'));
        self::assertNull($this->show($id)['archived_at']);
    }

    /**
     * @throws JsonException
     */
    public function testUnarchivingAnActiveCompanyIsANoOp(): void
    {
        $id = $this->create('Acme');

        $response = $this->jsonRequest('POST', '/api/companies/'.$id.'/unarchive', headers: $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertNull($this->show($id)['archived_at']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function archiveActionProvider(): iterable
    {
        yield 'archive' => ['archive'];
        yield 'unarchive' => ['unarchive'];
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('archiveActionProvider')]
    public function testArchivingAnotherUsersCompanyIsNotFound(string $action): void
    {
        $this->createUser('other@example.com');
        $other = $this->bearer($this->login('other@example.com')['access_token']);
        $theirs = $this->field($this->jsonRequest('POST', '/api/companies', ['name' => 'Theirs'], $other), 'id');

        $response = $this->jsonRequest('POST', '/api/companies/'.$theirs.'/'.$action, headers: $this->auth);

        $this->assertJsonError($response, Response::HTTP_NOT_FOUND, 'Company not found.');
        $check = $this->jsonRequest('GET', '/api/companies/'.$theirs, headers: $other);
        self::assertNull($this->decode($check)['archived_at'], 'Unchanged');
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('archiveActionProvider')]
    public function testArchivingAnUnknownCompanyIsNotFound(string $action): void
    {
        $response = $this->jsonRequest('POST', '/api/companies/'.CompanyId::generate().'/'.$action, headers: $this->auth);

        $this->assertJsonError($response, Response::HTTP_NOT_FOUND, 'Company not found.');
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('archiveActionProvider')]
    public function testArchivingRequiresAuthentication(string $action): void
    {
        $response = $this->jsonRequest('POST', '/api/companies/'.CompanyId::generate().'/'.$action);

        $this->assertJsonError($response, Response::HTTP_UNAUTHORIZED);
    }

    // ---- helpers ----------------------------------------------------------

    /**
     * Creates a company through the API and returns its id.
     *
     * @throws JsonException
     */
    private function create(string $name, ?string $overview = null): string
    {
        $response = $this->jsonRequest('POST', '/api/companies', ['name' => $name, 'overview' => $overview], $this->auth);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        return $this->field($response, 'id');
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private function show(string $id): array
    {
        $response = $this->jsonRequest('GET', '/api/companies/'.$id, headers: $this->auth);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return $this->decode($response);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws JsonException
     */
    private function list(string $query = ''): array
    {
        $response = $this->jsonRequest('GET', '/api/companies'.$query, headers: $this->auth);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $decoded = $this->decode($response);
        self::assertIsList($decoded);
        /** @var list<array<string, mixed>> $companies */
        $companies = $decoded;

        return $companies;
    }

    /**
     * Saves a company for the test user directly, for state the API can't
     * create yet (products, positions, archiving).
     *
     * @param Closure(CompanyEntity): mixed $build
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function seed(Closure $build, string $name): CompanyEntity
    {
        $em = $this->em();
        $company = new CompanyEntity($this->reloadedUser(), $name);
        $build($company);
        $em->persist($company);
        $em->flush();

        return $company;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function reloadedUser(): User
    {
        return self::findOrFail($this->em(), User::class, $this->user->id);
    }

    private function companies(): CompanyRepository
    {
        return self::getContainer()->get(CompanyRepository::class);
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
