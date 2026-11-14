<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use App\Tests\Functional\UsesMediaStorage;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use JsonException;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function array_map;
use function array_replace;
use function is_string;
use function str_replace;
use function strtoupper;

final class PurgeTest extends ApiTestCase
{
    use UsesMediaStorage;

    private const array NOTHING = ['companies' => 0, 'products' => 0, 'projects' => 0, 'positions' => 0, 'journal_entries' => 0, 'categories' => 0, 'tags' => 0];

    /** @var array<string, string> the user's own token */
    private array $user;
    private string $userId;
    /** @var array<string, string> the admin's own token */
    private array $admin;
    private string $adminId;

    /**
     * @throws JsonException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->createUser('admin@example.com', roles: ['ROLE_ADMIN']);
        $this->admin = $this->bearer($this->login('admin@example.com')['access_token']);
        $this->adminId = $this->id($this->admin);
        $this->user = $this->signIn();
        $this->userId = $this->id($this->user);
    }

    /**
     * @throws Exception
     * @throws FilesystemException
     * @throws JsonException
     */
    public function testPurgingACompanyErasesEverythingUnderIt(): void
    {
        $company = $this->createdId('/api/companies', ['name' => 'Acme'], $this->user);
        $product = $this->createdId('/api/companies/'.$company.'/products', ['name' => 'Widget'], $this->user);
        $project = $this->createdId('/api/products/'.$product.'/projects', ['name' => 'Launch'], $this->user);
        $position = $this->createdId('/api/companies/'.$company.'/positions', ['title' => 'Developer', 'start_date' => '2020-01-15'], $this->user);
        $entry = $this->createdId('/api/projects/'.$project.'/journal-entries', ['title' => 'Kickoff', 'entry' => 'Secret plans.'], $this->user);
        $this->attach($entry, self::png());
        $this->jsonRequest('POST', '/api/products/'.$product.'/archive', headers: $this->user);
        $kept = $this->createdId('/api/companies', ['name' => 'Keep Co'], $this->user);
        $keptEntry = $this->createdId('/api/projects/'.$this->createdId('/api/products/'.$this->createdId('/api/companies/'.$kept.'/products', ['name' => 'Gadget'], $this->user).'/projects', ['name' => 'Other'], $this->user).'/journal-entries', ['title' => 'Kept', 'entry' => 'Stays.'], $this->user);
        $keptFile = $this->attach($keptEntry, self::png('kept.png'));
        $as = $this->impersonate();

        $expected = array_replace(self::NOTHING, ['companies' => 1, 'products' => 1, 'projects' => 1, 'positions' => 1, 'journal_entries' => 1]);
        $dryRun = $this->jsonRequest('POST', '/api/purge/companies/'.$company.'?dry_run=1', headers: $as);
        self::assertSame(Response::HTTP_OK, $dryRun->getStatusCode(), (string) $dryRun->getContent());
        self::assertSame(['dry_run' => true, 'purged' => $expected, 'untagged' => ['projects' => 0, 'journal_entries' => 0], 'files' => 1], $this->decode($dryRun));
        self::assertSame(Response::HTTP_OK, $this->jsonRequest('GET', '/api/companies/'.$company, headers: $this->user)->getStatusCode(), 'A dry run changes nothing');

        $response = $this->jsonRequest('POST', '/api/purge/companies/'.$company, headers: $as);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['dry_run' => false, 'purged' => $expected, 'untagged' => ['projects' => 0, 'journal_entries' => 0], 'files' => 1], $this->decode($response));
        foreach (['/api/companies/'.$company, '/api/products/'.$product, '/api/projects/'.$project, '/api/positions/'.$position, '/api/journal-entries/'.$entry, '/api/companies/'.$company.'/history'] as $uri) {
            self::assertSame(Response::HTTP_NOT_FOUND, $this->jsonRequest('GET', $uri, headers: $this->user)->getStatusCode(), $uri);
        }
        self::assertSame(0, $this->eventsOf([$company, $product, $project, $position, $entry]), 'History erased');
        self::assertSame([$this->userId.'/'.$keptFile], self::storedPaths(), 'Its file deleted, others kept');
        self::assertSame(['Keep Co'], array_column($this->getItems('/api/companies?include_archived=1', $this->user), 'name'));

        $this->rebuildProjections();
        self::assertSame(['Keep Co'], array_column($this->getItems('/api/companies?include_archived=1', $this->user), 'name'), 'A rebuild brings nothing back');
        self::assertSame(['Kept'], array_column($this->getItems('/api/journal-entries?include_archived=1', $this->user), 'title'));
    }

    /**
     * @throws Exception
     * @throws JsonException
     */
    public function testPurgingATagUntagsWhatKeepsLiving(): void
    {
        $category = $this->createdId('/api/categories', ['name' => 'Skill'], $this->user);
        $php = $this->createdId('/api/categories/'.$category.'/tags', ['name' => 'php'], $this->user);
        $sql = $this->createdId('/api/categories/'.$category.'/tags', ['name' => 'sql'], $this->user);
        $company = $this->createdId('/api/companies', ['name' => 'Acme'], $this->user);
        $product = $this->createdId('/api/companies/'.$company.'/products', ['name' => 'Widget'], $this->user);
        $project = $this->createdId('/api/products/'.$product.'/projects', ['name' => 'Launch', 'tag_ids' => [$php, $sql]], $this->user);
        $entry = $this->createdId('/api/projects/'.$project.'/journal-entries', ['title' => 'Kickoff', 'entry' => 'Body', 'tag_ids' => [$php]], $this->user);

        $response = $this->jsonRequest('POST', '/api/purge/tags/'.$php, headers: $this->impersonate());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['dry_run' => false, 'purged' => array_replace(self::NOTHING, ['tags' => 1]), 'untagged' => ['projects' => 1, 'journal_entries' => 1], 'files' => 0], $this->decode($response));
        self::assertSame(['sql'], array_column(self::array($this->getJson('/api/projects/'.$project, $this->user)['tags'] ?? null), 'name'));
        self::assertSame([], $this->getJson('/api/journal-entries/'.$entry, $this->user)['tags'] ?? null);
        self::assertSame(0, $this->eventsOf([$php]));

        $history = $this->getItems('/api/projects/'.$project.'/history', $this->user);
        $last = $history[1] ?? [];
        self::assertSame(['project.retagged', $this->adminId], [$last['type'] ?? null, $last['impersonated_by'] ?? null], 'Untagging is an ordinary change, done by the admin');
        self::assertSame([$sql], self::stringList(self::array($last['data'] ?? null)['tag_ids'] ?? null), 'No surviving history points at the purged tag');
    }

    /**
     * @throws Exception
     * @throws JsonException
     */
    public function testPurgingACategoryTakesItsTags(): void
    {
        $category = $this->createdId('/api/categories', ['name' => 'Skill'], $this->user);
        $php = $this->createdId('/api/categories/'.$category.'/tags', ['name' => 'php'], $this->user);
        $other = $this->createdId('/api/categories', ['name' => 'Domain'], $this->user);
        $this->createdId('/api/categories/'.$other.'/tags', ['name' => 'fintech'], $this->user);

        $response = $this->jsonRequest('POST', '/api/purge/categories/'.$category, headers: $this->impersonate());

        self::assertSame(array_replace(self::NOTHING, ['categories' => 1, 'tags' => 1]), $this->decode($response)['purged'] ?? null);
        self::assertSame(['Domain'], array_column($this->getItems('/api/categories', $this->user), 'name'));
        self::assertSame(0, $this->eventsOf([$category, $php]));
    }

    /**
     * @throws JsonException
     */
    public function testOnlyAnImpersonatingAdminMayPurgeAndOnlyTheirUsersRecords(): void
    {
        $company = $this->createdId('/api/companies', ['name' => 'Acme'], $this->user);
        $theirs = $this->createdId('/api/companies', ['name' => 'Theirs'], $this->signIn('other@example.com'));

        $error = 'Only an admin impersonating this user may do this.';
        $this->assertJsonError($this->jsonRequest('POST', '/api/purge/companies/'.$company, headers: $this->user), Response::HTTP_FORBIDDEN, $error);
        $this->assertJsonError($this->jsonRequest('POST', '/api/purge/companies/'.$company, headers: $this->admin), Response::HTTP_FORBIDDEN, $error);

        $as = $this->impersonate();
        $this->assertJsonError($this->jsonRequest('POST', '/api/purge/companies/'.$theirs, headers: $as), Response::HTTP_NOT_FOUND, 'Not found.');
        $this->assertJsonError($this->jsonRequest('POST', '/api/purge/products/'.$company, headers: $as), Response::HTTP_NOT_FOUND, 'Not found.');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->jsonRequest('POST', '/api/purge/users/'.$company, headers: $as)->getStatusCode());
        self::assertCount(1, $this->getItems('/api/companies', $this->user));
    }

    /**
     * @return array<string, string>
     *
     * @throws JsonException
     */
    private function impersonate(): array
    {
        $response = $this->jsonRequest('POST', '/api/admin/users/'.$this->userId.'/impersonate', headers: $this->admin);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return $this->bearer($this->field($response, 'access_token'));
    }

    /**
     * @throws JsonException
     */
    private function attach(string $entry, UploadedFile $file): string
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        foreach ($this->user as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $this->client->request('POST', '/api/journal-entries/'.$entry.'/media', files: ['file' => $file], server: $server);
        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        return $this->field($this->client->getResponse(), 'id');
    }

    /**
     * @param list<string> $streamIds
     *
     * @throws Exception
     */
    private function eventsOf(array $streamIds): int
    {
        $count = 0;
        foreach ($streamIds as $id) {
            $events = self::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM event_store WHERE stream_id = :id', ['id' => $id]);
            self::assertIsNumeric($events);
            $count += (int) $events;
        }

        return $count;
    }

    private function rebuildProjections(): void
    {
        self::assertNotNull(self::$kernel);
        $tester = new CommandTester(new Application(self::$kernel)->find('app:projections:rebuild'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
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
     * @return array<array-key, mixed>
     */
    private static function array(mixed $value): array
    {
        self::assertIsArray($value);

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        self::assertIsList($value);

        return array_map(static fn (mixed $item): string => is_string($item) ? $item : '', $value);
    }
}
