<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Company\CompanyId;
use App\Company\ProductId;
use App\Tests\Functional\ApiTestCase;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function str_repeat;

final class ProductControllerTest extends ApiTestCase
{
    /** @var array<string, string> */
    private array $auth;
    private string $companyId;

    /**
     * @throws JsonException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->createUser();
        $this->auth = $this->bearer($this->login()['access_token']);
        $this->companyId = $this->post('/api/companies', ['name' => 'Acme'], $this->auth);
    }

    // ---- create and list --------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testCreateAndShow(): void
    {
        $id = $this->createProduct(' Widget ', 'Our widget');

        $product = $this->show($id);

        self::assertSame($id, $product['id']);
        self::assertSame($this->companyId, $product['company_id']);
        self::assertSame('Widget', $product['name'], 'Trimmed');
        self::assertSame('Our widget', $product['overview']);
        self::assertSame(0, $product['project_count']);
        self::assertNull($product['archived_at']);
    }

    /**
     * @throws JsonException
     */
    public function testListIsSortedByNameAndHidesArchived(): void
    {
        $this->createProduct('Widget');
        $this->createProduct('Gadget');
        $retired = $this->createProduct('Retired');
        $this->jsonRequest('POST', '/api/products/'.$retired.'/archive', headers: $this->auth);

        self::assertSame(['Gadget', 'Widget'], array_column($this->list(), 'name'));
        self::assertSame(['Gadget', 'Retired', 'Widget'], array_column($this->list('?include_archived=1'), 'name'));
    }

    /**
     * @throws JsonException
     */
    public function testArchivedCompanyHidesItsProducts(): void
    {
        $this->createProduct('Widget');
        $this->jsonRequest('POST', '/api/companies/'.$this->companyId.'/archive', headers: $this->auth);

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
        $response = $this->jsonRequest('POST', '/api/companies/'.$this->companyId.'/products', $payload, $this->auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('name', $this->firstViolationPath($response));
        self::assertSame([], $this->list(), 'Nothing saved');
    }

    /**
     * @throws JsonException
     */
    public function testAnotherUsersOrAnUnknownCompanyIsNotFound(): void
    {
        $theirCompany = $this->post('/api/companies', ['name' => 'Theirs'], $this->otherUser());

        foreach ([$theirCompany, (string) CompanyId::generate()] as $companyId) {
            $create = $this->jsonRequest('POST', '/api/companies/'.$companyId.'/products', ['name' => 'Widget'], $this->auth);
            $this->assertJsonError($create, Response::HTTP_NOT_FOUND, 'Company not found.');

            $list = $this->jsonRequest('GET', '/api/companies/'.$companyId.'/products', headers: $this->auth);
            $this->assertJsonError($list, Response::HTTP_NOT_FOUND, 'Company not found.');
        }
    }

    /**
     * @throws JsonException
     */
    public function testListIsPaged(): void
    {
        foreach (['Widget', 'Gadget', 'Gizmo'] as $name) {
            $this->createProduct($name);
        }

        self::assertSame(['Widget'], array_column($this->list('?page=2&per_page=2'), 'name'));
        self::assertSame(['Gadget'], array_column($this->list('?per_page=1'), 'name'));
    }

    // ---- show and edit ----------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testAnotherUsersOrAnUnknownProductIsNotFound(): void
    {
        $other = $this->otherUser();
        $theirCompany = $this->post('/api/companies', ['name' => 'Theirs'], $other);
        $theirProduct = $this->post('/api/companies/'.$theirCompany.'/products', ['name' => 'Theirs'], $other);

        foreach ([$theirProduct, (string) ProductId::generate()] as $productId) {
            foreach ([['GET', ''], ['PATCH', ''], ['POST', '/archive'], ['POST', '/unarchive']] as [$method, $suffix]) {
                $response = $this->jsonRequest($method, '/api/products/'.$productId.$suffix, 'PATCH' === $method ? ['name' => 'Mine'] : null, $this->auth);
                $this->assertJsonError($response, Response::HTTP_NOT_FOUND, 'Product not found.');
            }
        }

        $check = $this->jsonRequest('GET', '/api/products/'.$theirProduct, headers: $other);
        self::assertSame('Theirs', $this->field($check, 'name'), 'Unchanged');
    }

    /**
     * @throws JsonException
     */
    public function testEditChangesOnlyTheFieldsSent(): void
    {
        $id = $this->createProduct('Widget', 'Our widget');

        $response = $this->jsonRequest('PATCH', '/api/products/'.$id, ['name' => 'Gizmo'], $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('Gizmo', $this->show($id)['name']);
        self::assertSame('Our widget', $this->show($id)['overview']);

        $this->jsonRequest('PATCH', '/api/products/'.$id, ['overview' => ''], $this->auth);
        self::assertNull($this->show($id)['overview'], 'An empty overview clears it');
        self::assertSame('Gizmo', $this->show($id)['name']);
    }

    /**
     * @throws JsonException
     */
    public function testInvalidEditIsRejected(): void
    {
        $id = $this->createProduct('Widget');

        $response = $this->jsonRequest('PATCH', '/api/products/'.$id, ['name' => '  '], $this->auth);

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
        $id = $this->createProduct('Widget');

        $archive = $this->jsonRequest('POST', '/api/products/'.$id.'/archive', headers: $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $archive->getStatusCode());
        $clock->sleep(3600);
        $this->jsonRequest('POST', '/api/products/'.$id.'/archive', headers: $this->auth);
        self::assertSame('2026-03-04T05:06:07+00:00', $this->show($id)['archived_at'], 'Archiving twice keeps the first time');

        $unarchive = $this->jsonRequest('POST', '/api/products/'.$id.'/unarchive', headers: $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $unarchive->getStatusCode());
        self::assertNull($this->show($id)['archived_at']);
        self::assertSame(['Widget'], array_column($this->list(), 'name'));
    }

    /**
     * @throws JsonException
     */
    public function testProductsRequireAuthentication(): void
    {
        $list = $this->jsonRequest('GET', '/api/companies/'.$this->companyId.'/products');
        $show = $this->jsonRequest('GET', '/api/products/'.ProductId::generate());

        $this->assertJsonError($list, Response::HTTP_UNAUTHORIZED);
        $this->assertJsonError($show, Response::HTTP_UNAUTHORIZED);
    }

    // ---- helpers ----------------------------------------------------------

    /**
     * @throws JsonException
     */
    private function createProduct(string $name, ?string $overview = null): string
    {
        return $this->post('/api/companies/'.$this->companyId.'/products', ['name' => $name, 'overview' => $overview], $this->auth);
    }

    /**
     * POSTs a create request and returns the new id.
     *
     * @param array<string, mixed>  $body
     * @param array<string, string> $auth
     *
     * @throws JsonException
     */
    private function post(string $uri, array $body, array $auth): string
    {
        $response = $this->jsonRequest('POST', $uri, $body, $auth);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        return $this->field($response, 'id');
    }

    /**
     * @return array<string, string>
     *
     * @throws JsonException
     */
    private function otherUser(): array
    {
        $this->createUser('other@example.com');

        return $this->bearer($this->login('other@example.com')['access_token']);
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private function show(string $id): array
    {
        $response = $this->jsonRequest('GET', '/api/products/'.$id, headers: $this->auth);
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
        $response = $this->jsonRequest('GET', '/api/companies/'.$this->companyId.'/products'.$query, headers: $this->auth);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return $this->items($response);
    }
}
