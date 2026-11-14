<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function array_keys;
use function count;
use function is_array;
use function json_decode;
use function json_encode;
use function ksort;
use function str_replace;

use const JSON_THROW_ON_ERROR;

final class ImportControllerTest extends ApiTestCase
{
    /**
     * @throws JsonException
     */
    public function testAnImportRestoresEveryRecordAndItsHistory(): void
    {
        self::mockTime('2026-03-04 05:06:07 UTC');
        $original = $this->signIn('original@example.com');
        $company = $this->createdId('/api/companies', ['name' => 'Acme', 'overview' => "Two\nlines, Ünïcode"], $original);
        $product = $this->createdId('/api/companies/'.$company.'/products', ['name' => 'Widget'], $original);
        $category = $this->createdId('/api/categories', ['name' => 'Skill'], $original);
        $php = $this->createdId('/api/categories/'.$category.'/tags', ['name' => 'php'], $original);
        $sql = $this->createdId('/api/categories/'.$category.'/tags', ['name' => 'sql'], $original);
        $project = $this->createdId('/api/products/'.$product.'/projects', ['name' => 'Launch', 'tag_ids' => [$php]], $original);
        $this->createdId('/api/companies/'.$company.'/positions', ['title' => 'Developer', 'start_date' => '2020-01-15', 'end_date' => '2023-06-30', 'reason_left' => 'Moved on'], $original);
        self::mockTime('2026-03-04 05:10:00 UTC'); // later, within the access token's 15 minutes
        $this->createdId('/api/projects/'.$project.'/journal-entries', ['title' => 'Kickoff', 'entry' => 'Agreed on the scope.', 'tag_ids' => [$php, $sql]], $original);
        $this->jsonRequest('PATCH', '/api/companies/'.$company, ['name' => 'Acme Inc'], $original);
        $this->jsonRequest('PATCH', '/api/projects/'.$project, ['tag_ids' => [$sql]], $original);
        $this->jsonRequest('POST', '/api/tags/'.$php.'/archive', headers: $original);
        $export = $this->getJson('/api/me/export', $original);

        self::mockTime('2026-03-04 05:14:00 UTC');
        $restored = $this->signIn('restored@example.com');
        $response = $this->import($export, $restored);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['records' => 8, 'events' => 12], $this->decode($response));

        $again = $this->getJson('/api/me/export', $restored);
        self::assertSame(self::withoutIds($export), self::withoutIds($again), 'Same records and history, apart from ids and the account');

        // The restored records are live aggregates: they load, change and keep their history
        $restoredCompany = self::list($again, 'companies')[0]['id'];
        self::assertIsString($restoredCompany);
        self::assertNotSame($company, $restoredCompany, 'New ids');
        $rename = $this->jsonRequest('PATCH', '/api/companies/'.$restoredCompany, ['name' => 'Acme Ltd'], $restored);
        self::assertSame(Response::HTTP_NO_CONTENT, $rename->getStatusCode(), (string) $rename->getContent());
        $history = $this->getItems('/api/companies/'.$restoredCompany.'/history', $restored);
        self::assertSame(['2026-03-04T05:06:07+00:00', '2026-03-04T05:10:00+00:00', '2026-03-04T05:14:00+00:00'], array_column($history, 'recorded_at'));

        // The original account is untouched
        self::assertSame('Acme Inc', $this->getJson('/api/companies/'.$company, $original)['name']);
    }

    /**
     * @throws JsonException
     */
    public function testImportsOnlyIntoAnAccountWithNoRecords(): void
    {
        $auth = $this->signIn();
        $this->createdId('/api/companies', ['name' => 'Acme'], $auth);
        $export = $this->getJson('/api/me/export', $auth);

        $this->assertJsonError($this->import($export, $auth), Response::HTTP_CONFLICT, 'Imports go into an account with no records. Sign up for a new account (or delete this one\'s records) first.');
        self::assertCount(1, $this->getItems('/api/companies', $auth));
    }

    /**
     * @throws JsonException
     */
    public function testRejectsFilesThatAreNotImportableExports(): void
    {
        $theirs = $this->signIn('theirs@example.com');
        $theirCompany = $this->createdId('/api/companies', ['name' => 'Theirs'], $theirs);
        $mine = $this->signIn();
        $product = '01a1da4c-fb84-7d3a-9e81-18d96905f2f5';
        $created = ['stream_type' => 'company', 'stream_id' => $theirCompany, 'version' => 1, 'type' => 'company.created', 'recorded_at' => '2026-03-04T05:06:07+00:00', 'data' => ['company_id' => $theirCompany, 'owner_id' => $product, 'name' => 'Acme', 'overview' => null, 'created_at' => '2026-03-04T05:06:07+00:00']];
        $file = static fn (array ...$events): array => ['format' => 'worklore-export', 'version' => 2, 'events' => $events];

        $cases = [
            'not an export' => [['hello' => 'world'], 'This is not a Worklore export.'],
            'version 1' => [['format' => 'worklore-export', 'version' => 1], 'This export has no event history (format version 1). Export your data again, then import the new file.'],
            'unknown event type' => [$file(['type' => 'company.exploded'] + $created), 'Event 1 in the export cannot be read: Unknown event type "company.exploded".'],
            'another record type\'s event' => [$file(['type' => 'tag.created'] + $created), 'Event 1 in the export does not belong to its record\'s type.'],
            'unreadable data' => [$file(['data' => ['name' => 'Acme']] + $created), 'Event 1 in the export cannot be read: Event "company.created" has no "company_id".'],
            'out of order' => [$file(['version' => 2] + $created), 'Event 1 in the export is version 2 of its record, out of order.'],
            'not created first' => [$file(['type' => 'company.renamed', 'data' => ['company_id' => $theirCompany, 'name' => 'X']] + $created), 'Event 1 in the export does not create its record.'],
            'someone else\'s record' => [
                $file($created, ['stream_type' => 'product', 'stream_id' => $product, 'type' => 'product.created', 'data' => ['product_id' => $product, 'company_id' => '01a1da4c-fb17-7f70-8ee4-23c119e614cd', 'owner_id' => $product, 'name' => 'Widget', 'overview' => null, 'created_at' => '2026-03-04T05:06:07+00:00']] + $created),
                'Event 2 in the export refers to a record that is not in the export.',
            ],
        ];
        foreach ($cases as $case => [$body, $error]) {
            $response = $this->import($body, $mine);
            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), $case.': '.$response->getContent());
            self::assertSame(['error' => $error], $this->decode($response), $case);
        }

        self::assertSame([], $this->getItems('/api/companies', $mine), 'Nothing imported');
        self::assertSame('Theirs', $this->getJson('/api/companies/'.$theirCompany, $theirs)['name']);
    }

    /**
     * @throws JsonException
     */
    public function testADuplicateNameInTheFileIsAConflictAndImportsNothing(): void
    {
        $auth = $this->signIn();
        $a = '01a1da4c-fb84-7d3a-9e81-18d96905f2f5';
        $b = '01a1da4c-fb17-7f70-8ee4-23c119e614cd';
        $created = static fn (string $id): array => ['stream_type' => 'company', 'stream_id' => $id, 'version' => 1, 'type' => 'company.created', 'recorded_at' => '2026-03-04T05:06:07+00:00', 'data' => ['company_id' => $id, 'owner_id' => $a, 'name' => 'Acme', 'overview' => null, 'created_at' => '2026-03-04T05:06:07+00:00']];

        $response = $this->import(['format' => 'worklore-export', 'version' => 2, 'events' => [$created($a), $created($b)]], $auth);

        $this->assertJsonError($response, Response::HTTP_CONFLICT, 'You already have a company with this name.');
        self::assertSame([], $this->getItems('/api/companies', $auth));
    }

    /**
     * @throws JsonException
     */
    public function testImportRequiresAuthentication(): void
    {
        $this->assertJsonError($this->jsonRequest('POST', '/api/me/import', ['format' => 'worklore-export']), Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param array<array-key, mixed> $file a decoded export, or something posing as one
     * @param array<string, string>   $auth
     *
     * @throws JsonException
     */
    private function import(array $file, array $auth): Response
    {
        $body = [];
        foreach ($file as $key => $value) {
            $body[(string) $key] = $value;
        }

        return $this->jsonRequest('POST', '/api/me/import', $body, $auth);
    }

    /**
     * The export with every id replaced by its position in the event list
     * ("#1", "#2", ...) and the account and export time left out, so two
     * accounts' exports compare equal when they hold the same data.
     *
     * @param array<array-key, mixed> $export
     *
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private static function withoutIds(array $export): array
    {
        $account = $export['account'] ?? null;
        self::assertIsArray($account);
        $ids = [];
        foreach (self::list($export, 'events') as $event) {
            $id = $event['stream_id'] ?? null;
            self::assertIsString($id);
            $ids[$id] ??= '#'.(count($ids) + 1);
        }
        $owner = $account['id'] ?? null;
        self::assertIsString($owner);
        $ids[$owner] = '#owner';

        unset($export['account'], $export['exported_at']);
        $json = json_encode($export, JSON_THROW_ON_ERROR);
        $json = str_replace(array_keys($ids), $ids, $json);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return self::sortedData($decoded);
    }

    /**
     * jsonb doesn't keep an event's key order; sort each event's data.
     *
     * @param array<array-key, mixed> $export
     *
     * @return array<array-key, mixed>
     */
    private static function sortedData(array $export): array
    {
        $events = self::list($export, 'events');
        foreach ($events as $i => $event) {
            $data = $event['data'] ?? null;
            self::assertIsArray($data);
            ksort($data);
            $events[$i]['data'] = $data;
        }
        $export['events'] = $events;

        return $export;
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
