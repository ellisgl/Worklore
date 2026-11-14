<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventSourcing;

use App\Tests\Functional\ApiTestCase;
use JsonException;
use Monolog\Handler\TestHandler;
use Monolog\Level;

final class DomainEventListenersTest extends ApiTestCase
{
    /**
     * @throws JsonException
     */
    public function testEverySavedEventIsLoggedWithItsIdsButNotItsText(): void
    {
        $auth = $this->signIn();
        $owner = $this->getJson('/api/me', $auth)['id'] ?? null;
        $company = $this->createdId('/api/companies', ['name' => 'Secret Corp', 'overview' => 'Private notes'], $auth);
        self::assertSame(
            [[Level::Info, 'company.created', ['event' => 'company.created', 'company_id' => $company, 'owner_id' => $owner]]],
            self::logged(),
        );

        // Each request boots a fresh kernel, so the log starts empty again
        $this->jsonRequest('PATCH', '/api/companies/'.$company, ['name' => 'Renamed Corp'], $auth);
        self::assertSame([[Level::Info, 'company.renamed', ['event' => 'company.renamed', 'company_id' => $company]]], self::logged());
    }

    /**
     * @return list<array{Level, string, array<array-key, mixed>}>
     */
    private static function logged(): array
    {
        // A test-only handler (monolog.yaml, when@test); PHPStan reads the dev container
        $log = self::getContainer()->get('monolog.handler.domain'); // @phpstan-ignore symfonyContainer.serviceNotFound
        self::assertInstanceOf(TestHandler::class, $log);

        $records = [];
        foreach ($log->getRecords() as $record) {
            $records[] = [$record->level, $record->message, $record->context];
        }

        return $records;
    }
}
