<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Taxonomy\CategoryId;
use App\Tests\Functional\ApiTestCase;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function str_repeat;

final class CategoryControllerTest extends ApiTestCase
{
    /** @var array<string, string> */
    private array $auth;

    /**
     * @throws JsonException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->signIn();
    }

    // ---- create and list --------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testCreateAndShow(): void
    {
        $id = $this->createCategory(' Skill ', 'Things I can do');

        $category = $this->show($id);

        self::assertSame($id, $category['id']);
        self::assertSame('Skill', $category['name'], 'Trimmed');
        self::assertSame('Things I can do', $category['description']);
        self::assertSame(0, $category['tag_count']);
        self::assertNull($category['archived_at']);
    }

    /**
     * @throws JsonException
     */
    public function testListIsSortedHidesArchivedAndIsPaged(): void
    {
        $this->createCategory('Skill');
        $this->createCategory('Area');
        $old = $this->createCategory('Old');
        $this->jsonRequest('POST', '/api/categories/'.$old.'/archive', headers: $this->auth);

        self::assertSame(['Area', 'Skill'], array_column($this->list(), 'name'));
        self::assertSame(['Area', 'Old', 'Skill'], array_column($this->list('?include_archived=1'), 'name'));
        self::assertSame(['Skill'], array_column($this->list('?page=2&per_page=1'), 'name'));
    }

    /**
     * @throws JsonException
     */
    public function testSameNamesAreAllowed(): void
    {
        $this->createCategory('Area');
        $this->createCategory('area');

        self::assertCount(2, $this->list());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidCreateProvider(): iterable
    {
        yield 'missing name' => [['description' => 'x']];
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
        $response = $this->jsonRequest('POST', '/api/categories', $payload, $this->auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('name', $this->firstViolationPath($response));
        self::assertSame([], $this->list(), 'Nothing saved');
    }

    // ---- show and edit ----------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testAnotherUsersOrAnUnknownCategoryIsNotFound(): void
    {
        $other = $this->signIn('other@example.com');
        $theirs = $this->createdId('/api/categories', ['name' => 'Theirs'], $other);

        foreach ([$theirs, (string) CategoryId::generate()] as $categoryId) {
            foreach ([['GET', ''], ['PATCH', ''], ['POST', '/archive'], ['POST', '/unarchive']] as [$method, $suffix]) {
                $response = $this->jsonRequest($method, '/api/categories/'.$categoryId.$suffix, 'PATCH' === $method ? ['name' => 'Mine'] : null, $this->auth);
                $this->assertJsonError($response, Response::HTTP_NOT_FOUND, 'Category not found.');
            }
        }

        $check = $this->jsonRequest('GET', '/api/categories/'.$theirs, headers: $other);
        self::assertSame('Theirs', $this->field($check, 'name'), 'Unchanged');
    }

    /**
     * @throws JsonException
     */
    public function testEditChangesOnlyTheFieldsSent(): void
    {
        $id = $this->createCategory('Skill', 'Things I can do');

        $response = $this->jsonRequest('PATCH', '/api/categories/'.$id, ['name' => 'Skills'], $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('Skills', $this->show($id)['name']);
        self::assertSame('Things I can do', $this->show($id)['description']);

        $this->jsonRequest('PATCH', '/api/categories/'.$id, ['description' => ''], $this->auth);
        self::assertNull($this->show($id)['description'], 'An empty description clears it');
    }

    /**
     * @throws JsonException
     */
    public function testInvalidEditIsRejected(): void
    {
        $id = $this->createCategory('Skill');

        $response = $this->jsonRequest('PATCH', '/api/categories/'.$id, ['name' => '  '], $this->auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Skill', $this->show($id)['name']);
    }

    // ---- archive ----------------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testArchiveAndUnarchive(): void
    {
        $clock = self::mockTime('2026-03-04 05:06:07 UTC');
        $id = $this->createCategory('Skill');

        $archive = $this->jsonRequest('POST', '/api/categories/'.$id.'/archive', headers: $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $archive->getStatusCode());
        $clock->sleep(3600);
        $this->jsonRequest('POST', '/api/categories/'.$id.'/archive', headers: $this->auth);
        self::assertSame('2026-03-04T05:06:07+00:00', $this->show($id)['archived_at'], 'Archiving twice keeps the first time');

        $unarchive = $this->jsonRequest('POST', '/api/categories/'.$id.'/unarchive', headers: $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $unarchive->getStatusCode());
        self::assertNull($this->show($id)['archived_at']);
        self::assertSame(['Skill'], array_column($this->list(), 'name'));
    }

    /**
     * @throws JsonException
     */
    public function testCategoriesRequireAuthentication(): void
    {
        $this->assertJsonError($this->jsonRequest('GET', '/api/categories'), Response::HTTP_UNAUTHORIZED);
        $this->assertJsonError($this->jsonRequest('GET', '/api/categories/'.CategoryId::generate()), Response::HTTP_UNAUTHORIZED);
    }

    // ---- helpers ----------------------------------------------------------

    /**
     * @throws JsonException
     */
    private function createCategory(string $name, ?string $description = null): string
    {
        return $this->createdId('/api/categories', ['name' => $name, 'description' => $description], $this->auth);
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private function show(string $id): array
    {
        return $this->getJson('/api/categories/'.$id, $this->auth);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws JsonException
     */
    private function list(string $query = ''): array
    {
        return $this->getItems('/api/categories'.$query, $this->auth);
    }
}
