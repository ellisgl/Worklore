<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Company\ProjectId;
use App\Journal\JournalEntryId;
use App\Taxonomy\TagId;
use App\Tests\Functional\ApiTestCase;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function is_array;
use function str_repeat;

final class JournalEntryControllerTest extends ApiTestCase
{
    /** @var array<string, string> */
    private array $auth;
    private string $projectId;
    private string $php;
    private string $backend;

    /**
     * @throws JsonException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->signIn();
        $this->projectId = $this->projectFor($this->auth);
        $skill = $this->createdId('/api/categories', ['name' => 'Skill'], $this->auth);
        $area = $this->createdId('/api/categories', ['name' => 'Area'], $this->auth);
        $this->php = $this->createdId('/api/categories/'.$skill.'/tags', ['name' => 'php'], $this->auth);
        $this->backend = $this->createdId('/api/categories/'.$area.'/tags', ['name' => 'backend'], $this->auth);
    }

    // ---- create and list --------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testCreateAndShow(): void
    {
        $id = $this->createEntry(['title' => ' Kickoff ', 'entry' => ' Agreed on the scope. ', 'tag_ids' => [$this->php, $this->backend, $this->php]]);

        $entry = $this->show($id);

        self::assertSame($id, $entry['id']);
        self::assertSame($this->projectId, $entry['project_id']);
        self::assertSame('Kickoff', $entry['title'], 'Trimmed');
        self::assertSame('Agreed on the scope.', $entry['entry'], 'Trimmed');
        self::assertNull($entry['archived_at']);
        self::assertSame(['backend', 'php'], $this->tagNames($entry), 'By category (Area, Skill); duplicates dropped');
        self::assertSame([
            ['id' => $this->backend, 'name' => 'backend', 'category_id' => $this->tagCategory($this->backend), 'category_name' => 'Area'],
        ], [$this->tags($entry)[0]]);
    }

    /**
     * @throws JsonException
     */
    public function testListIsNewestFirstHidesArchivedAndIsPaged(): void
    {
        $clock = self::mockTime('2026-01-01 09:00:00 UTC');
        $this->createEntry(['title' => 'First', 'entry' => 'Body']);
        $clock->sleep(60);
        $second = $this->createEntry(['title' => 'Second', 'entry' => 'Body']);
        $clock->sleep(60);
        $this->createEntry(['title' => 'Third', 'entry' => 'Body']);
        $this->jsonRequest('POST', '/api/journal-entries/'.$second.'/archive', headers: $this->auth);

        self::assertSame(['Third', 'First'], array_column($this->list(), 'title'));
        self::assertSame(['Third', 'Second', 'First'], array_column($this->list('?include_archived=1'), 'title'));
        self::assertSame(['Second'], array_column($this->list('?include_archived=1&page=2&per_page=1'), 'title'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidCreateProvider(): iterable
    {
        yield 'missing title' => [['entry' => 'Body'], 'title'];
        yield 'blank title' => [['title' => ' ', 'entry' => 'Body'], 'title'];
        yield 'title too long' => [['title' => str_repeat('x', 256), 'entry' => 'Body'], 'title'];
        yield 'missing entry' => [['title' => 'Kickoff'], 'entry'];
        yield 'blank entry' => [['title' => 'Kickoff', 'entry' => "  \n "], 'entry'];
        yield 'tag id not a uuid' => [['title' => 'Kickoff', 'entry' => 'Body', 'tag_ids' => ['php']], 'tag_ids[0]'];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws JsonException
     */
    #[DataProvider('invalidCreateProvider')]
    public function testInvalidCreateIsRejected(array $payload, string $field): void
    {
        $response = $this->jsonRequest('POST', $this->entriesUri(), $payload, $this->auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($field, $this->firstViolationPath($response));
        self::assertSame([], $this->list(), 'Nothing saved');
    }

    /**
     * @throws JsonException
     */
    public function testAnotherUsersOrAnUnknownProjectIsNotFound(): void
    {
        $theirProject = $this->projectFor($this->signIn('other@example.com'));

        foreach ([$theirProject, (string) ProjectId::generate()] as $projectId) {
            $create = $this->jsonRequest('POST', '/api/projects/'.$projectId.'/journal-entries', ['title' => 'Kickoff', 'entry' => 'Body'], $this->auth);
            $this->assertJsonError($create, Response::HTTP_NOT_FOUND, 'Project not found.');

            $list = $this->jsonRequest('GET', '/api/projects/'.$projectId.'/journal-entries', headers: $this->auth);
            $this->assertJsonError($list, Response::HTTP_NOT_FOUND, 'Project not found.');
        }
    }

    /**
     * @throws JsonException
     */
    public function testAnotherUsersOrAnUnknownTagIsNotFound(): void
    {
        $other = $this->signIn('other@example.com');
        $theirCategory = $this->createdId('/api/categories', ['name' => 'Theirs'], $other);
        $theirTag = $this->createdId('/api/categories/'.$theirCategory.'/tags', ['name' => 'theirs'], $other);
        $id = $this->createEntry(['title' => 'Kickoff', 'entry' => 'Body', 'tag_ids' => [$this->php]]);

        foreach ([$theirTag, (string) TagId::generate()] as $tagId) {
            $create = $this->jsonRequest('POST', $this->entriesUri(), ['title' => 'Kickoff', 'entry' => 'Body', 'tag_ids' => [$this->php, $tagId]], $this->auth);
            $this->assertJsonError($create, Response::HTTP_NOT_FOUND, 'Tag not found.');

            $edit = $this->jsonRequest('PATCH', '/api/journal-entries/'.$id, ['title' => 'Changed', 'tag_ids' => [$tagId]], $this->auth);
            $this->assertJsonError($edit, Response::HTTP_NOT_FOUND, 'Tag not found.');
        }

        self::assertCount(1, $this->list(), 'Nothing saved');
        self::assertSame('Kickoff', $this->show($id)['title'], 'The edit is all or nothing');
        self::assertSame(['php'], $this->tagNames($this->show($id)));
    }

    // ---- show and edit ----------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testAnotherUsersOrAnUnknownEntryIsNotFound(): void
    {
        $other = $this->signIn('other@example.com');
        $theirs = $this->createdId('/api/projects/'.$this->projectFor($other).'/journal-entries', ['title' => 'Theirs', 'entry' => 'Body'], $other);

        foreach ([$theirs, (string) JournalEntryId::generate()] as $entryId) {
            foreach ([['GET', ''], ['PATCH', ''], ['POST', '/archive'], ['POST', '/unarchive']] as [$method, $suffix]) {
                $response = $this->jsonRequest($method, '/api/journal-entries/'.$entryId.$suffix, 'PATCH' === $method ? ['title' => 'Mine'] : null, $this->auth);
                $this->assertJsonError($response, Response::HTTP_NOT_FOUND, 'Journal entry not found.');
            }
        }

        $check = $this->jsonRequest('GET', '/api/journal-entries/'.$theirs, headers: $other);
        self::assertSame('Theirs', $this->field($check, 'title'), 'Unchanged');
    }

    /**
     * @throws JsonException
     */
    public function testEditChangesOnlyTheFieldsSent(): void
    {
        $id = $this->createEntry(['title' => 'Kickoff', 'entry' => 'Agreed on the scope.', 'tag_ids' => [$this->php]]);

        $response = $this->jsonRequest('PATCH', '/api/journal-entries/'.$id, ['title' => 'Kick-off'], $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $entry = $this->show($id);
        self::assertSame('Kick-off', $entry['title']);
        self::assertSame('Agreed on the scope.', $entry['entry']);
        self::assertSame(['php'], $this->tagNames($entry), 'No tag_ids: tags unchanged');

        $this->jsonRequest('PATCH', '/api/journal-entries/'.$id, ['entry' => 'Scope moved.', 'tag_ids' => [$this->backend]], $this->auth);
        self::assertSame('Scope moved.', $this->show($id)['entry']);
        self::assertSame(['backend'], $this->tagNames($this->show($id)), 'tag_ids replaces the tags');

        $this->jsonRequest('PATCH', '/api/journal-entries/'.$id, ['tag_ids' => []], $this->auth);
        self::assertSame([], $this->tagNames($this->show($id)), 'An empty list removes them');
    }

    /**
     * @throws JsonException
     */
    public function testInvalidEditIsRejected(): void
    {
        $id = $this->createEntry(['title' => 'Kickoff', 'entry' => 'Body']);

        $blankTitle = $this->jsonRequest('PATCH', '/api/journal-entries/'.$id, ['title' => ' '], $this->auth);
        $blankEntry = $this->jsonRequest('PATCH', '/api/journal-entries/'.$id, ['entry' => ' '], $this->auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $blankTitle->getStatusCode());
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $blankEntry->getStatusCode());
        self::assertSame('Body', $this->show($id)['entry']);
    }

    // ---- archive ----------------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testArchiveAndUnarchive(): void
    {
        $clock = self::mockTime('2026-03-04 05:06:07 UTC');
        $id = $this->createEntry(['title' => 'Kickoff', 'entry' => 'Body']);

        $archive = $this->jsonRequest('POST', '/api/journal-entries/'.$id.'/archive', headers: $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $archive->getStatusCode());
        $clock->sleep(3600);
        $this->jsonRequest('POST', '/api/journal-entries/'.$id.'/archive', headers: $this->auth);
        self::assertSame('2026-03-04T05:06:07+00:00', $this->show($id)['archived_at'], 'Archiving twice keeps the first time');

        $unarchive = $this->jsonRequest('POST', '/api/journal-entries/'.$id.'/unarchive', headers: $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $unarchive->getStatusCode());
        self::assertNull($this->show($id)['archived_at']);
        self::assertSame(['Kickoff'], array_column($this->list(), 'title'));
    }

    /**
     * @throws JsonException
     */
    public function testJournalRequiresAuthentication(): void
    {
        $this->assertJsonError($this->jsonRequest('GET', $this->entriesUri()), Response::HTTP_UNAUTHORIZED);
        $this->assertJsonError($this->jsonRequest('GET', '/api/journal-entries/'.JournalEntryId::generate()), Response::HTTP_UNAUTHORIZED);
    }

    // ---- helpers ----------------------------------------------------------

    private function entriesUri(): string
    {
        return '/api/projects/'.$this->projectId.'/journal-entries';
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws JsonException
     */
    private function createEntry(array $body): string
    {
        return $this->createdId($this->entriesUri(), $body, $this->auth);
    }

    /**
     * A new company, product and project for this user; returns the project id.
     *
     * @param array<string, string> $auth
     *
     * @throws JsonException
     */
    private function projectFor(array $auth): string
    {
        $company = $this->createdId('/api/companies', ['name' => 'Acme'], $auth);
        $product = $this->createdId('/api/companies/'.$company.'/products', ['name' => 'Widget'], $auth);

        return $this->createdId('/api/products/'.$product.'/projects', ['name' => 'Launch'], $auth);
    }

    /**
     * @throws JsonException
     */
    private function tagCategory(string $tagId): string
    {
        return $this->field($this->jsonRequest('GET', '/api/tags/'.$tagId, headers: $this->auth), 'category_id');
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private function show(string $id): array
    {
        return $this->getJson('/api/journal-entries/'.$id, $this->auth);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws JsonException
     */
    private function list(string $query = ''): array
    {
        return $this->getItems($this->entriesUri().$query, $this->auth);
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @return list<array<array-key, mixed>>
     */
    private function tags(array $entry): array
    {
        $tags = $entry['tags'] ?? null;
        self::assertIsList($tags);
        $list = [];
        foreach ($tags as $tag) {
            self::assertTrue(is_array($tag));
            $list[] = $tag;
        }

        return $list;
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @return list<mixed>
     */
    private function tagNames(array $entry): array
    {
        return array_column($this->tags($entry), 'name');
    }
}
