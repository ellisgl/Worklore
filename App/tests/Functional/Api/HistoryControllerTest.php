<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Company\CompanyId;
use App\Tests\Functional\ApiTestCase;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

use function array_column;

final class HistoryControllerTest extends ApiTestCase
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

    /**
     * @throws JsonException
     */
    public function testShowsEveryChangeAsItsEvent(): void
    {
        $clock = self::mockTime('2026-03-04 05:06:07 UTC');
        $acme = $this->createdId('/api/companies', ['name' => 'Acme'], $this->auth);
        $clock->sleep(60);
        $this->jsonRequest('PATCH', '/api/companies/'.$acme, ['name' => 'Acme Ltd', 'overview' => 'Widgets'], $this->auth);
        $this->jsonRequest('PATCH', '/api/companies/'.$acme, ['name' => 'Acme Ltd'], $this->auth);   // no change: no event
        $clock->sleep(60);
        $this->jsonRequest('POST', '/api/companies/'.$acme.'/archive', headers: $this->auth);

        $history = $this->getJson('/api/companies/'.$acme.'/history', $this->auth);
        $entries = $this->items($this->jsonRequest('GET', '/api/companies/'.$acme.'/history', headers: $this->auth));

        self::assertSame(4, $history['total']);
        self::assertSame([1, 2, 3, 4], array_column($entries, 'version'));
        self::assertSame(['company.created', 'company.renamed', 'company.overview_changed', 'company.archived'], array_column($entries, 'type'));
        self::assertSame('2026-03-04T05:06:07+00:00', $entries[0]['recorded_at']);
        self::assertEquals(['company_id' => $acme, 'name' => 'Acme Ltd'], $entries[1]['data'], 'jsonb keeps the keys, not their order');
        self::assertSame('2026-03-04T05:08:07+00:00', $entries[3]['recorded_at']);
    }

    /**
     * @throws JsonException
     */
    public function testEveryRecordTypeHasAHistory(): void
    {
        $company = $this->createdId('/api/companies', ['name' => 'Acme'], $this->auth);
        $product = $this->createdId('/api/companies/'.$company.'/products', ['name' => 'Widget'], $this->auth);
        $project = $this->createdId('/api/products/'.$product.'/projects', ['name' => 'Launch'], $this->auth);
        $position = $this->createdId('/api/companies/'.$company.'/positions', ['title' => 'Dev', 'start_date' => '2020-01-01', 'end_date' => '2021-01-01'], $this->auth);
        $skill = $this->createdId('/api/categories', ['name' => 'Skill'], $this->auth);
        $area = $this->createdId('/api/categories', ['name' => 'Area'], $this->auth);
        $tag = $this->createdId('/api/categories/'.$skill.'/tags', ['name' => 'php'], $this->auth);
        $this->jsonRequest('PATCH', '/api/tags/'.$tag, ['category_id' => $area], $this->auth);
        $entry = $this->createdId('/api/projects/'.$project.'/journal-entries', ['title' => 'Kickoff', 'entry' => 'Body', 'tag_ids' => [$tag]], $this->auth);

        foreach ([
            'companies/'.$company => ['company.created'],
            'products/'.$product => ['product.created'],
            'projects/'.$project => ['project.created'],
            'positions/'.$position => ['position.created', 'position.ended'],
            'categories/'.$skill => ['category.created'],
            'tags/'.$tag => ['tag.created', 'tag.moved'],
            'journal-entries/'.$entry => ['journal_entry.written'],
        ] as $path => $types) {
            self::assertSame($types, array_column($this->getItems('/api/'.$path.'/history', $this->auth), 'type'), $path);
        }
    }

    /**
     * @throws JsonException
     */
    public function testAnotherUsersOrAnUnknownRecordIsNotFound(): void
    {
        $theirs = $this->createdId('/api/companies', ['name' => 'Theirs'], $this->signIn('other@example.com'));

        foreach ([$theirs, (string) CompanyId::generate()] as $id) {
            $this->assertJsonError($this->jsonRequest('GET', '/api/companies/'.$id.'/history', headers: $this->auth), Response::HTTP_NOT_FOUND, 'Company not found.');
        }
        $this->assertJsonError($this->jsonRequest('GET', '/api/tags/'.CompanyId::generate().'/history', headers: $this->auth), Response::HTTP_NOT_FOUND, 'Tag not found.');
    }

    /**
     * @throws JsonException
     */
    public function testHistoryIsPagedAndNeedsAToken(): void
    {
        $acme = $this->createdId('/api/companies', ['name' => 'Acme'], $this->auth);
        $this->jsonRequest('PATCH', '/api/companies/'.$acme, ['name' => 'Acme 2'], $this->auth);
        $this->jsonRequest('PATCH', '/api/companies/'.$acme, ['name' => 'Acme 3'], $this->auth);

        $page = $this->getJson('/api/companies/'.$acme.'/history?page=2&per_page=2', $this->auth);

        self::assertSame(3, $page['total']);
        self::assertSame([3], array_column($this->items($this->jsonRequest('GET', '/api/companies/'.$acme.'/history?page=2&per_page=2', headers: $this->auth)), 'version'));
        $this->assertJsonError($this->jsonRequest('GET', '/api/companies/'.$acme.'/history'), Response::HTTP_UNAUTHORIZED);
    }
}
