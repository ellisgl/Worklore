<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Taxonomy\CategoryId;
use App\Taxonomy\TagId;
use App\Tests\Functional\ApiTestCase;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function str_repeat;

final class TagControllerTest extends ApiTestCase
{
    /** @var array<string, string> */
    private array $auth;
    private string $categoryId;

    /**
     * @throws JsonException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->createUser();
        $this->auth = $this->bearer($this->login()['access_token']);
        $this->categoryId = $this->post('/api/categories', ['name' => 'Skill'], $this->auth);
    }

    // ---- create and list --------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testCreateAndShow(): void
    {
        $id = $this->createTag(' php ');

        $tag = $this->show($id);

        self::assertSame($id, $tag['id']);
        self::assertSame($this->categoryId, $tag['category_id']);
        self::assertSame('Skill', $tag['category_name']);
        self::assertSame('php', $tag['name'], 'Trimmed');
        self::assertNull($tag['archived_at']);
    }

    /**
     * @throws JsonException
     */
    public function testNameIsUniqueInTheCategoryIgnoringCase(): void
    {
        $archived = $this->createTag('php');
        $this->jsonRequest('POST', '/api/tags/'.$archived.'/archive', headers: $this->auth);

        $response = $this->jsonRequest('POST', $this->tagsUri(), ['name' => 'PHP'], $this->auth);

        $this->assertJsonError($response, Response::HTTP_CONFLICT, 'This category already has a tag named "PHP".');

        $area = $this->post('/api/categories', ['name' => 'Area'], $this->auth);
        $this->post('/api/categories/'.$area.'/tags', ['name' => 'php'], $this->auth);  // fine elsewhere
    }

    /**
     * @throws JsonException
     */
    public function testListsAreSortedHideArchivedAndArePaged(): void
    {
        $this->createTag('sql');
        $this->createTag('php');
        $old = $this->createTag('cobol');
        $this->jsonRequest('POST', '/api/tags/'.$old.'/archive', headers: $this->auth);
        $area = $this->post('/api/categories', ['name' => 'Area'], $this->auth);
        $this->post('/api/categories/'.$area.'/tags', ['name' => 'backend'], $this->auth);

        self::assertSame(['php', 'sql'], array_column($this->list(), 'name'));
        self::assertSame(['cobol', 'php', 'sql'], array_column($this->list('?include_archived=1'), 'name'));
        self::assertSame(['sql'], array_column($this->list('?page=2&per_page=1'), 'name'));

        $all = $this->jsonRequest('GET', '/api/tags', headers: $this->auth);
        self::assertSame(['backend', 'php', 'sql'], array_column($this->items($all), 'name'), 'By category (Area, Skill), then name');
        self::assertSame(['Area', 'Skill', 'Skill'], array_column($this->items($all), 'category_name'));
    }

    /**
     * @throws JsonException
     */
    public function testArchivedCategoryHidesItsTags(): void
    {
        $this->createTag('php');
        $this->jsonRequest('POST', '/api/categories/'.$this->categoryId.'/archive', headers: $this->auth);

        self::assertSame([], $this->list());
        self::assertSame(['php'], array_column($this->list('?include_archived=1'), 'name'));
        self::assertSame([], $this->items($this->jsonRequest('GET', '/api/tags', headers: $this->auth)));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidCreateProvider(): iterable
    {
        yield 'missing name' => [[]];
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
        $response = $this->jsonRequest('POST', $this->tagsUri(), $payload, $this->auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('name', $this->firstViolationPath($response));
        self::assertSame([], $this->list(), 'Nothing saved');
    }

    /**
     * @throws JsonException
     */
    public function testAnotherUsersOrAnUnknownCategoryIsNotFound(): void
    {
        $theirCategory = $this->post('/api/categories', ['name' => 'Theirs'], $this->otherUser());

        foreach ([$theirCategory, (string) CategoryId::generate()] as $categoryId) {
            $create = $this->jsonRequest('POST', '/api/categories/'.$categoryId.'/tags', ['name' => 'php'], $this->auth);
            $this->assertJsonError($create, Response::HTTP_NOT_FOUND, 'Category not found.');

            $list = $this->jsonRequest('GET', '/api/categories/'.$categoryId.'/tags', headers: $this->auth);
            $this->assertJsonError($list, Response::HTTP_NOT_FOUND, 'Category not found.');
        }
    }

    // ---- show and edit ----------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testAnotherUsersOrAnUnknownTagIsNotFound(): void
    {
        $other = $this->otherUser();
        $theirCategory = $this->post('/api/categories', ['name' => 'Theirs'], $other);
        $theirTag = $this->post('/api/categories/'.$theirCategory.'/tags', ['name' => 'theirs'], $other);

        foreach ([$theirTag, (string) TagId::generate()] as $tagId) {
            foreach ([['GET', ''], ['PATCH', ''], ['POST', '/archive'], ['POST', '/unarchive']] as [$method, $suffix]) {
                $response = $this->jsonRequest($method, '/api/tags/'.$tagId.$suffix, 'PATCH' === $method ? ['name' => 'mine'] : null, $this->auth);
                $this->assertJsonError($response, Response::HTTP_NOT_FOUND, 'Tag not found.');
            }
        }

        $check = $this->jsonRequest('GET', '/api/tags/'.$theirTag, headers: $other);
        self::assertSame('theirs', $this->field($check, 'name'), 'Unchanged');
    }

    /**
     * @throws JsonException
     */
    public function testRename(): void
    {
        $id = $this->createTag('php');
        $this->createTag('sql');

        $rename = $this->jsonRequest('PATCH', '/api/tags/'.$id, ['name' => 'PHP 8'], $this->auth);
        $sameNameNewCase = $this->jsonRequest('PATCH', '/api/tags/'.$id, ['name' => 'php 8'], $this->auth);
        $clash = $this->jsonRequest('PATCH', '/api/tags/'.$id, ['name' => 'SQL'], $this->auth);
        $blank = $this->jsonRequest('PATCH', '/api/tags/'.$id, ['name' => ' '], $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $rename->getStatusCode(), (string) $rename->getContent());
        self::assertSame(Response::HTTP_NO_CONTENT, $sameNameNewCase->getStatusCode(), 'Its own name is no clash');
        $this->assertJsonError($clash, Response::HTTP_CONFLICT, 'This category already has a tag named "SQL".');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $blank->getStatusCode());
        self::assertSame('php 8', $this->show($id)['name']);
    }

    /**
     * @throws JsonException
     */
    public function testMoveToAnotherCategory(): void
    {
        $id = $this->createTag('backend');
        $area = $this->post('/api/categories', ['name' => 'Area'], $this->auth);

        $response = $this->jsonRequest('PATCH', '/api/tags/'.$id, ['category_id' => $area], $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($area, $this->show($id)['category_id']);
        self::assertSame([], $this->list());
        self::assertSame(['backend'], array_column($this->items($this->jsonRequest('GET', '/api/categories/'.$area.'/tags', headers: $this->auth)), 'name'));
    }

    /**
     * @throws JsonException
     */
    public function testMoveChecksTheTargetCategory(): void
    {
        $id = $this->createTag('php');
        $area = $this->post('/api/categories', ['name' => 'Area'], $this->auth);
        $this->post('/api/categories/'.$area.'/tags', ['name' => 'PHP'], $this->auth);
        $theirCategory = $this->post('/api/categories', ['name' => 'Theirs'], $this->otherUser());

        $clash = $this->jsonRequest('PATCH', '/api/tags/'.$id, ['category_id' => $area], $this->auth);
        $renamedMove = $this->jsonRequest('PATCH', '/api/tags/'.$id, ['category_id' => $area, 'name' => 'php 8'], $this->auth);
        $theirs = $this->jsonRequest('PATCH', '/api/tags/'.$id, ['category_id' => $theirCategory], $this->auth);
        $notUuid = $this->jsonRequest('PATCH', '/api/tags/'.$id, ['category_id' => 'area'], $this->auth);

        $this->assertJsonError($clash, Response::HTTP_CONFLICT, 'This category already has a tag named "php".');
        self::assertSame(Response::HTTP_NO_CONTENT, $renamedMove->getStatusCode(), 'A new name that is free there is fine');
        $this->assertJsonError($theirs, Response::HTTP_NOT_FOUND, 'Category not found.');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $notUuid->getStatusCode());
        self::assertSame('category_id', $this->firstViolationPath($notUuid));
        self::assertSame($area, $this->show($id)['category_id']);
        self::assertSame('php 8', $this->show($id)['name']);
    }

    // ---- archive ----------------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testArchiveAndUnarchive(): void
    {
        $clock = self::mockTime('2026-03-04 05:06:07 UTC');
        $id = $this->createTag('php');

        $archive = $this->jsonRequest('POST', '/api/tags/'.$id.'/archive', headers: $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $archive->getStatusCode());
        $clock->sleep(3600);
        $this->jsonRequest('POST', '/api/tags/'.$id.'/archive', headers: $this->auth);
        self::assertSame('2026-03-04T05:06:07+00:00', $this->show($id)['archived_at'], 'Archiving twice keeps the first time');

        $unarchive = $this->jsonRequest('POST', '/api/tags/'.$id.'/unarchive', headers: $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $unarchive->getStatusCode());
        self::assertNull($this->show($id)['archived_at']);
        self::assertSame(['php'], array_column($this->list(), 'name'));
    }

    /**
     * @throws JsonException
     */
    public function testTagsRequireAuthentication(): void
    {
        $this->assertJsonError($this->jsonRequest('GET', '/api/tags'), Response::HTTP_UNAUTHORIZED);
        $this->assertJsonError($this->jsonRequest('GET', $this->tagsUri()), Response::HTTP_UNAUTHORIZED);
        $this->assertJsonError($this->jsonRequest('GET', '/api/tags/'.TagId::generate()), Response::HTTP_UNAUTHORIZED);
    }

    // ---- helpers ----------------------------------------------------------

    private function tagsUri(): string
    {
        return '/api/categories/'.$this->categoryId.'/tags';
    }

    /**
     * @throws JsonException
     */
    private function createTag(string $name): string
    {
        return $this->post($this->tagsUri(), ['name' => $name], $this->auth);
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
        $response = $this->jsonRequest('GET', '/api/tags/'.$id, headers: $this->auth);
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
        $response = $this->jsonRequest('GET', $this->tagsUri().$query, headers: $this->auth);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return $this->items($response);
    }
}
