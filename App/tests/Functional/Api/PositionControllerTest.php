<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Company\CompanyId;
use App\Company\PositionId;
use App\Tests\Functional\ApiTestCase;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function str_repeat;

final class PositionControllerTest extends ApiTestCase
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
        $id = $this->createPosition(['title' => ' Developer ', 'start_date' => '2020-01-15', 'overview' => 'Backend work']);

        $position = $this->show($id);

        self::assertSame($id, $position['id']);
        self::assertSame($this->companyId, $position['company_id']);
        self::assertSame('Developer', $position['title'], 'Trimmed');
        self::assertSame('Backend work', $position['overview']);
        self::assertSame('2020-01-15', $position['start_date']);
        self::assertNull($position['end_date']);
        self::assertTrue($position['current']);
        self::assertNull($position['reason_left']);
        self::assertNull($position['archived_at']);
    }

    /**
     * @throws JsonException
     */
    public function testCreateAnEndedPosition(): void
    {
        $id = $this->createPosition(['title' => 'Intern', 'start_date' => '2018-06-01', 'end_date' => '2018-08-31', 'reason_left' => 'Internship ended']);

        $position = $this->show($id);

        self::assertSame('2018-08-31', $position['end_date']);
        self::assertFalse($position['current']);
        self::assertSame('Internship ended', $position['reason_left']);
    }

    /**
     * @throws JsonException
     */
    public function testCreateEndingBeforeItStartsIsRejected(): void
    {
        $response = $this->jsonRequest('POST', $this->positionsUri(), ['title' => 'Dev', 'start_date' => '2020-01-15', 'end_date' => '2019-12-31'], $this->auth);

        $this->assertJsonError($response, Response::HTTP_UNPROCESSABLE_ENTITY, 'A position cannot end before it starts');
        self::assertSame([], $this->list('?include_archived=1'), 'Nothing saved');
    }

    /**
     * @throws JsonException
     */
    public function testListIsNewestFirstAndHidesArchived(): void
    {
        $this->createPosition(['title' => 'Developer', 'start_date' => '2019-03-01']);
        $this->createPosition(['title' => 'Senior', 'start_date' => '2022-06-01']);
        $hidden = $this->createPosition(['title' => 'Hidden', 'start_date' => '2021-01-01']);
        $this->jsonRequest('POST', '/api/positions/'.$hidden.'/archive', headers: $this->auth);

        self::assertSame(['Senior', 'Developer'], array_column($this->list(), 'title'));
        self::assertSame(['Senior', 'Hidden', 'Developer'], array_column($this->list('?include_archived=1'), 'title'));
    }

    /**
     * @throws JsonException
     */
    public function testCurrentListsOpenPositionsAcrossCompanies(): void
    {
        $globex = $this->post('/api/companies', ['name' => 'Globex'], $this->auth);
        $this->createPosition(['title' => 'Consultant', 'start_date' => '2023-01-01']);
        $this->post('/api/companies/'.$globex.'/positions', ['title' => 'Lead', 'start_date' => '2024-01-01'], $this->auth);
        $this->createPosition(['title' => 'Junior', 'start_date' => '2020-01-01', 'end_date' => '2022-12-31']);

        $response = $this->jsonRequest('GET', '/api/positions/current', headers: $this->auth);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['Lead', 'Consultant'], array_column($this->decode($response), 'title'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidCreateProvider(): iterable
    {
        yield 'missing title' => [['start_date' => '2020-01-01'], 'title'];
        yield 'blank title' => [['title' => '   ', 'start_date' => '2020-01-01'], 'title'];
        yield 'title too long' => [['title' => str_repeat('x', 256), 'start_date' => '2020-01-01'], 'title'];
        yield 'missing start date' => [['title' => 'Dev'], 'start_date'];
        yield 'bad start date' => [['title' => 'Dev', 'start_date' => '2020-13-01'], 'start_date'];
        yield 'bad end date' => [['title' => 'Dev', 'start_date' => '2020-01-01', 'end_date' => 'soon'], 'end_date'];
        yield 'reason too long' => [['title' => 'Dev', 'start_date' => '2020-01-01', 'end_date' => '2021-01-01', 'reason_left' => str_repeat('x', 256)], 'reason_left'];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws JsonException
     */
    #[DataProvider('invalidCreateProvider')]
    public function testInvalidCreateIsRejected(array $payload, string $field): void
    {
        $response = $this->jsonRequest('POST', $this->positionsUri(), $payload, $this->auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame($field, $this->firstViolationPath($response));
        self::assertSame([], $this->list(), 'Nothing saved');
    }

    /**
     * @throws JsonException
     */
    public function testAnotherUsersOrAnUnknownCompanyIsNotFound(): void
    {
        $theirCompany = $this->post('/api/companies', ['name' => 'Theirs'], $this->otherUser());

        foreach ([$theirCompany, (string) CompanyId::generate()] as $companyId) {
            $create = $this->jsonRequest('POST', '/api/companies/'.$companyId.'/positions', ['title' => 'Dev', 'start_date' => '2020-01-01'], $this->auth);
            $this->assertJsonError($create, Response::HTTP_NOT_FOUND, 'Company not found.');

            $list = $this->jsonRequest('GET', '/api/companies/'.$companyId.'/positions', headers: $this->auth);
            $this->assertJsonError($list, Response::HTTP_NOT_FOUND, 'Company not found.');
        }
    }

    // ---- show and edit ----------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testAnotherUsersOrAnUnknownPositionIsNotFound(): void
    {
        $other = $this->otherUser();
        $theirCompany = $this->post('/api/companies', ['name' => 'Theirs'], $other);
        $theirPosition = $this->post('/api/companies/'.$theirCompany.'/positions', ['title' => 'Theirs', 'start_date' => '2020-01-01'], $other);

        $requests = [
            ['GET', '', null],
            ['PATCH', '', ['title' => 'Mine']],
            ['POST', '/end', ['end_date' => '2021-01-01']],
            ['POST', '/reopen', null],
            ['POST', '/archive', null],
            ['POST', '/unarchive', null],
        ];
        foreach ([$theirPosition, (string) PositionId::generate()] as $positionId) {
            foreach ($requests as [$method, $suffix, $body]) {
                $response = $this->jsonRequest($method, '/api/positions/'.$positionId.$suffix, $body, $this->auth);
                $this->assertJsonError($response, Response::HTTP_NOT_FOUND, 'Position not found.');
            }
        }

        $check = $this->jsonRequest('GET', '/api/positions/'.$theirPosition, headers: $other);
        self::assertSame('Theirs', $this->field($check, 'title'), 'Unchanged');
    }

    /**
     * @throws JsonException
     */
    public function testEditChangesOnlyTheFieldsSent(): void
    {
        $id = $this->createPosition(['title' => 'Developer', 'start_date' => '2020-01-15', 'overview' => 'Backend work']);

        $response = $this->jsonRequest('PATCH', '/api/positions/'.$id, ['title' => 'Engineer', 'start_date' => '2020-02-01'], $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $position = $this->show($id);
        self::assertSame('Engineer', $position['title']);
        self::assertSame('2020-02-01', $position['start_date']);
        self::assertSame('Backend work', $position['overview']);

        $this->jsonRequest('PATCH', '/api/positions/'.$id, ['overview' => ''], $this->auth);
        self::assertNull($this->show($id)['overview'], 'An empty overview clears it');
    }

    /**
     * @throws JsonException
     */
    public function testInvalidEditIsRejected(): void
    {
        $id = $this->createPosition(['title' => 'Developer', 'start_date' => '2020-01-15', 'end_date' => '2021-01-01']);

        $blank = $this->jsonRequest('PATCH', '/api/positions/'.$id, ['title' => '  '], $this->auth);
        $badDate = $this->jsonRequest('PATCH', '/api/positions/'.$id, ['start_date' => 'yesterday'], $this->auth);
        $afterEnd = $this->jsonRequest('PATCH', '/api/positions/'.$id, ['title' => 'Engineer', 'start_date' => '2021-06-01'], $this->auth);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $blank->getStatusCode());
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $badDate->getStatusCode());
        $this->assertJsonError($afterEnd, Response::HTTP_UNPROCESSABLE_ENTITY, 'A position cannot end before it starts');
        $position = $this->show($id);
        self::assertSame('Developer', $position['title'], 'Nothing applied: the edit is all or nothing');
        self::assertSame('2020-01-15', $position['start_date']);
    }

    // ---- end and reopen ---------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testEndAndReopen(): void
    {
        $id = $this->createPosition(['title' => 'Developer', 'start_date' => '2020-01-15']);

        $end = $this->jsonRequest('POST', '/api/positions/'.$id.'/end', ['end_date' => '2023-06-30', 'reason_left' => 'Moved on'], $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $end->getStatusCode(), (string) $end->getContent());
        $position = $this->show($id);
        self::assertSame('2023-06-30', $position['end_date']);
        self::assertFalse($position['current']);
        self::assertSame('Moved on', $position['reason_left']);

        $this->jsonRequest('POST', '/api/positions/'.$id.'/end', ['end_date' => '2023-07-31'], $this->auth);
        self::assertSame('2023-07-31', $this->show($id)['end_date'], 'Ending again corrects the date');
        self::assertNull($this->show($id)['reason_left']);

        $reopen = $this->jsonRequest('POST', '/api/positions/'.$id.'/reopen', headers: $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $reopen->getStatusCode());
        $position = $this->show($id);
        self::assertNull($position['end_date']);
        self::assertTrue($position['current']);
    }

    /**
     * @throws JsonException
     */
    public function testEndingBeforeTheStartIsRejected(): void
    {
        $id = $this->createPosition(['title' => 'Developer', 'start_date' => '2020-01-15']);

        $response = $this->jsonRequest('POST', '/api/positions/'.$id.'/end', ['end_date' => '2020-01-14'], $this->auth);

        $this->assertJsonError($response, Response::HTTP_UNPROCESSABLE_ENTITY, 'A position cannot end before it starts');
        self::assertTrue($this->show($id)['current']);
    }

    /**
     * @throws JsonException
     */
    public function testEndNeedsAValidDate(): void
    {
        $id = $this->createPosition(['title' => 'Developer', 'start_date' => '2020-01-15']);

        foreach ([[], ['end_date' => '31/12/2023']] as $body) {
            $response = $this->jsonRequest('POST', '/api/positions/'.$id.'/end', $body, $this->auth);
            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
            self::assertSame('end_date', $this->firstViolationPath($response));
        }
    }

    // ---- archive ----------------------------------------------------------

    /**
     * @throws JsonException
     */
    public function testArchiveAndUnarchive(): void
    {
        $clock = self::mockTime('2026-03-04 05:06:07 UTC');
        $id = $this->createPosition(['title' => 'Developer', 'start_date' => '2020-01-15']);

        $archive = $this->jsonRequest('POST', '/api/positions/'.$id.'/archive', headers: $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $archive->getStatusCode());
        $clock->sleep(3600);
        $this->jsonRequest('POST', '/api/positions/'.$id.'/archive', headers: $this->auth);
        self::assertSame('2026-03-04T05:06:07+00:00', $this->show($id)['archived_at'], 'Archiving twice keeps the first time');

        $unarchive = $this->jsonRequest('POST', '/api/positions/'.$id.'/unarchive', headers: $this->auth);
        self::assertSame(Response::HTTP_NO_CONTENT, $unarchive->getStatusCode());
        self::assertNull($this->show($id)['archived_at']);
        self::assertSame(['Developer'], array_column($this->list(), 'title'));
    }

    /**
     * @throws JsonException
     */
    public function testPositionsRequireAuthentication(): void
    {
        $this->assertJsonError($this->jsonRequest('GET', $this->positionsUri()), Response::HTTP_UNAUTHORIZED);
        $this->assertJsonError($this->jsonRequest('GET', '/api/positions/current'), Response::HTTP_UNAUTHORIZED);
        $this->assertJsonError($this->jsonRequest('GET', '/api/positions/'.PositionId::generate()), Response::HTTP_UNAUTHORIZED);
    }

    // ---- helpers ----------------------------------------------------------

    private function positionsUri(): string
    {
        return '/api/companies/'.$this->companyId.'/positions';
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws JsonException
     */
    private function createPosition(array $body): string
    {
        return $this->post($this->positionsUri(), $body, $this->auth);
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
        $response = $this->jsonRequest('GET', '/api/positions/'.$id, headers: $this->auth);
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
        $response = $this->jsonRequest('GET', $this->positionsUri().$query, headers: $this->auth);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $decoded = $this->decode($response);
        self::assertIsList($decoded);
        /** @var list<array<string, mixed>> $positions */
        $positions = $decoded;

        return $positions;
    }
}
