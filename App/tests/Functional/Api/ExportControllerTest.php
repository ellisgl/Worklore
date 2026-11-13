<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function array_keys;
use function is_array;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class ExportControllerTest extends ApiTestCase
{
    /**
     * @throws JsonException
     */
    public function testExportsEverythingTheUserOwns(): void
    {
        self::mockTime('2026-03-04 05:06:07 UTC');
        $auth = $this->signIn();
        $company = $this->createdId('/api/companies', ['name' => 'Acme', 'overview' => 'Ünïcode & "quotes"'], $auth);
        $product = $this->createdId('/api/companies/'.$company.'/products', ['name' => 'Widget'], $auth);
        $project = $this->createdId('/api/products/'.$product.'/projects', ['name' => 'Launch'], $auth);
        $position = $this->createdId('/api/companies/'.$company.'/positions', ['title' => 'Developer', 'start_date' => '2020-01-15', 'end_date' => '2023-06-30', 'reason_left' => 'Moved on'], $auth);
        $category = $this->createdId('/api/categories', ['name' => 'Skill'], $auth);
        $tag = $this->createdId('/api/categories/'.$category.'/tags', ['name' => 'php'], $auth);
        $entry = $this->createdId('/api/projects/'.$project.'/journal-entries', ['title' => 'Kickoff', 'entry' => 'Agreed on the scope.', 'tag_ids' => [$tag]], $auth);
        $this->jsonRequest('POST', '/api/products/'.$product.'/archive', headers: $auth);

        $response = $this->jsonRequest('GET', '/api/me/export', headers: $auth);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('attachment; filename=worklore-export-2026-03-04.json', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString("\n    ", (string) $response->getContent(), 'Pretty-printed');
        self::assertStringContainsString('Ünïcode', (string) $response->getContent(), 'Unicode unescaped');

        $export = $this->decode($response);
        self::assertSame(
            ['format', 'version', 'exported_at', 'account', 'companies', 'products', 'projects', 'positions', 'categories', 'tags', 'journal_entries'],
            array_keys($export),
        );
        self::assertSame('worklore-export', $export['format']);
        self::assertSame(1, $export['version']);
        self::assertSame('2026-03-04T05:06:07+00:00', $export['exported_at']);

        $account = $export['account'] ?? null;
        self::assertIsArray($account);
        self::assertSame(['id', 'email', 'created_at', 'archived_at'], array_keys($account), 'No password hash');
        self::assertSame('user@example.com', $account['email']);

        self::assertSame([['id' => $company, 'name' => 'Acme', 'overview' => 'Ünïcode & "quotes"', 'created_at' => '2026-03-04T05:06:07+00:00', 'archived_at' => null]], self::list($export, 'companies'));
        self::assertSame('2026-03-04T05:06:07+00:00', self::list($export, 'products')[0]['archived_at'], 'Archived records included');
        self::assertSame($company, self::list($export, 'products')[0]['company_id']);
        self::assertSame(['id' => $project, 'product_id' => $product, 'name' => 'Launch', 'overview' => null, 'created_at' => '2026-03-04T05:06:07+00:00', 'archived_at' => null, 'tag_ids' => []], self::list($export, 'projects')[0]);
        self::assertSame(
            ['id' => $position, 'company_id' => $company, 'title' => 'Developer', 'overview' => null, 'start_date' => '2020-01-15', 'end_date' => '2023-06-30', 'reason_left' => 'Moved on', 'created_at' => '2026-03-04T05:06:07+00:00', 'archived_at' => null],
            self::list($export, 'positions')[0],
        );
        self::assertSame([$category], array_column(self::list($export, 'categories'), 'id'));
        self::assertSame([['id' => $tag, 'category_id' => $category, 'name' => 'php', 'created_at' => '2026-03-04T05:06:07+00:00', 'archived_at' => null]], self::list($export, 'tags'));
        self::assertSame(
            [['id' => $entry, 'project_id' => $project, 'title' => 'Kickoff', 'entry' => 'Agreed on the scope.', 'created_at' => '2026-03-04T05:06:07+00:00', 'archived_at' => null, 'tag_ids' => [$tag]]],
            self::list($export, 'journal_entries'),
        );
    }

    /**
     * @throws JsonException
     */
    public function testExportsOnlyTheUsersOwnData(): void
    {
        $theirs = $this->signIn('other@example.com');
        $this->createdId('/api/companies', ['name' => 'Theirs'], $theirs);
        $this->createdId('/api/categories', ['name' => 'Theirs'], $theirs);
        $mine = $this->signIn();

        $export = $this->getJson('/api/me/export', $mine);

        foreach (['companies', 'products', 'projects', 'positions', 'categories', 'tags', 'journal_entries'] as $list) {
            self::assertSame([], self::list($export, $list), $list);
        }
        self::assertStringNotContainsString('other@example.com', json_encode($export, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws JsonException
     */
    public function testExportRequiresAuthentication(): void
    {
        $this->assertJsonError($this->jsonRequest('GET', '/api/me/export'), Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param array<array-key, mixed> $export
     *
     * @return list<array<array-key, mixed>>
     */
    private static function list(array $export, string $key): array
    {
        $value = $export[$key] ?? null;
        self::assertIsList($value);
        $rows = [];
        foreach ($value as $row) {
            self::assertTrue(is_array($row));
            $rows[] = $row;
        }

        return $rows;
    }
}
