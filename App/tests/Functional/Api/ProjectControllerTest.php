<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Company\ProductId;
use App\Company\ProjectId;
use App\Tests\Functional\ApiTestCase;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function str_repeat;

final class ProjectControllerTest extends ApiTestCase
{
    /** @var array<string, string> */
    private array $auth;
    private string $productId;

    /**
     * @throws JsonException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->signIn();
        $companyId = $this->createdId('/api/companies', ['name' => 'Acme'], $this->auth);
        $this->productId = $this->createdId('/api/companies/'.$companyId.'/products', ['name' => 'Widget'], $this->auth);
    }

    // ---- create and list --------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testCreateAndShow(): void
    {
        $id = $this->createProject(' Widget ', 'Our widget');

        $project = $this->show($id);

        self::assertSame($id, $project['id']);
        self::assertSame($this->productId, $project['product_id']);
        self::assertArrayHasKey('company_id', $project);
        self::assertSame('Widget', $project['name'], 'Trimmed');
        self::assertSame('Our widget', $project['overview']);
        self::assertNull($project['archived_at']);
    }

    /**
     * @throws JsonException
     */
    public function testListIsSortedByNameAndHidesArchived(): void
    {
        $this->createProject('Widget');
        $this->createProject('Gadget');
        $retired = $this->createProject('Retired');
        $this->jsonRequest('POST', '/api/projects/'.$retired.'/archive', headers: $this->auth);

        self::assertSame(['Gadget', 'Widget'], array_column($this->list(), 'name'));
        self::assertSame(['Gadget', 'Retired', 'Widget'], array_column($this->list('?include_archived=1'), 'name'));
    }

    /**
     * @throws JsonException
     */
    public function testArchivedProductHidesItsProjects(): void
    {
        $this->createProject('Widget');
        $this->jsonRequest('POST', '/api/products/'.$this->productId.'/archive', headers: $this->auth);

        self::assertSame([], $this->list());
        self::assertSame(['Widget'], array_column($this->list('?include_archived=1'), 'name'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidCreateProvider(): iterable
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
    #[DataProvider('invalidCreateProvider')]
    public function testInvalidCreateIsRejected(array $payload): void
    {
        $response = $this->jsonRequest('POST', '/api/products/'.$this->productId.'/projects', $payload, $this->auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('name', $this->firstViolationPath($response));
        self::assertSame([], $this->list(), 'Nothing saved');
    }

    /**
     * @throws JsonException
     */
    public function testAnotherUsersOrAnUnknownProductIsNotFound(): void
    {
        $other = $this->signIn('other@example.com');
        $theirCompany = $this->createdId('/api/companies', ['name' => 'Theirs'], $other);
        $theirProduct = $this->createdId('/api/companies/'.$theirCompany.'/products', ['name' => 'Theirs'], $other);

        foreach ([$theirProduct, (string) ProductId::generate()] as $productId) {
            $create = $this->jsonRequest('POST', '/api/products/'.$productId.'/projects', ['name' => 'Launch'], $this->auth);
            $this->assertJsonError($create, Response::HTTP_NOT_FOUND, 'Product not found.');

            $list = $this->jsonRequest('GET', '/api/products/'.$productId.'/projects', headers: $this->auth);
            $this->assertJsonError($list, Response::HTTP_NOT_FOUND, 'Product not found.');
        }
    }

    /**
     * @throws JsonException
     */
    public function testListIsPaged(): void
    {
        foreach (['Launch', 'Beta', 'Gamma'] as $name) {
            $this->createProject($name);
        }

        self::assertSame(['Launch'], array_column($this->list('?page=2&per_page=2'), 'name'));
        self::assertSame(['Beta'], array_column($this->list('?per_page=1'), 'name'));
    }

    // ---- show and edit ----------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testAnotherUsersOrAnUnknownProjectIsNotFound(): void
    {
        $other = $this->signIn('other@example.com');
        $theirCompany = $this->createdId('/api/companies', ['name' => 'Theirs'], $other);
        $theirProduct = $this->createdId('/api/companies/'.$theirCompany.'/products', ['name' => 'Theirs'], $other);
        $theirProject = $this->createdId('/api/products/'.$theirProduct.'/projects', ['name' => 'Theirs'], $other);

        foreach ([$theirProject, (string) ProjectId::generate()] as $projectId) {
            foreach ([['GET', ''], ['PATCH', ''], ['POST', '/archive'], ['POST', '/unarchive']] as [$method, $suffix]) {
                $response = $this->jsonRequest($method, '/api/projects/'.$projectId.$suffix, 'PATCH' === $method ? ['name' => 'Mine'] : null, $this->auth);
                $this->assertJsonError($response, Response::HTTP_NOT_FOUND, 'Project not found.');
            }
        }

        $check = $this->jsonRequest('GET', '/api/projects/'.$theirProject, headers: $other);
        self::assertSame('Theirs', $this->field($check, 'name'), 'Unchanged');
    }

    /**
     * @throws JsonException
     */
    public function testEditChangesOnlyTheFieldsSent(): void
    {
        $id = $this->createProject('Widget', 'Our widget');

        $response = $this->jsonRequest('PATCH', '/api/projects/'.$id, ['name' => 'Gizmo'], $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('Gizmo', $this->show($id)['name']);
        self::assertSame('Our widget', $this->show($id)['overview']);

        $this->jsonRequest('PATCH', '/api/projects/'.$id, ['overview' => ''], $this->auth);
        self::assertNull($this->show($id)['overview'], 'An empty overview clears it');
        self::assertSame('Gizmo', $this->show($id)['name']);
    }

    /**
     * @throws JsonException
     */
    public function testInvalidEditIsRejected(): void
    {
        $id = $this->createProject('Widget');

        $response = $this->jsonRequest('PATCH', '/api/projects/'.$id, ['name' => '  '], $this->auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Widget', $this->show($id)['name']);
    }

    // ---- archive ----------------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testArchiveAndUnarchive(): void
    {
        $clock = self::mockTime('2026-03-04 05:06:07 UTC');
        $id = $this->createProject('Widget');

        $archive = $this->jsonRequest('POST', '/api/projects/'.$id.'/archive', headers: $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $archive->getStatusCode());
        $clock->sleep(3600);
        $this->jsonRequest('POST', '/api/projects/'.$id.'/archive', headers: $this->auth);
        self::assertSame('2026-03-04T05:06:07+00:00', $this->show($id)['archived_at'], 'Archiving twice keeps the first time');

        $unarchive = $this->jsonRequest('POST', '/api/projects/'.$id.'/unarchive', headers: $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $unarchive->getStatusCode());
        self::assertNull($this->show($id)['archived_at']);
        self::assertSame(['Widget'], array_column($this->list(), 'name'));
    }

    /**
     * @throws JsonException
     */
    public function testProjectsRequireAuthentication(): void
    {
        $list = $this->jsonRequest('GET', '/api/products/'.$this->productId.'/projects');
        $show = $this->jsonRequest('GET', '/api/projects/'.ProjectId::generate());

        $this->assertJsonError($list, Response::HTTP_UNAUTHORIZED);
        $this->assertJsonError($show, Response::HTTP_UNAUTHORIZED);
    }

    // ---- helpers ----------------------------------------------------------

    /**
     * @throws JsonException
     */
    private function createProject(string $name, ?string $overview = null): string
    {
        return $this->createdId('/api/products/'.$this->productId.'/projects', ['name' => $name, 'overview' => $overview], $this->auth);
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private function show(string $id): array
    {
        return $this->getJson('/api/projects/'.$id, $this->auth);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws JsonException
     */
    private function list(string $query = ''): array
    {
        return $this->getItems('/api/products/'.$this->productId.'/projects'.$query, $this->auth);
    }
}
