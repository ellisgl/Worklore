<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Company\CompanyId;
use App\Company\Query\CompanyNames;
use App\Shared\UserId;
use App\Taxonomy\CategoryEntity;
use App\Taxonomy\CategoryRepository;
use App\Taxonomy\TagEntity;
use App\Tests\Functional\ApiTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two requests with the same name can both pass the handler's check before
 * either commits; the unique constraint stops the second. Here the check is
 * replaced with one that always says the name is free, so the constraint
 * (and the real PostgreSQL error) is what the request runs into.
 */
final class NameRaceTest extends ApiTestCase
{
    /**
     * @throws JsonException
     */
    public function testLosingACompanyNameRaceIsAConflict(): void
    {
        $auth = $this->signIn();
        // Swap the repository before any request uses it, and keep this
        // kernel (and so the swap) for the rest of the test.
        $this->client->disableReboot();
        self::getContainer()->set(CompanyNames::class, new readonly class(self::getContainer()->get(Connection::class)) extends CompanyNames {
            public function companyNameTaken(UserId $ownerId, string $name, ?CompanyId $except = null): bool
            {
                return false;
            }
        });
        $this->createdId('/api/companies', ['name' => 'Acme'], $auth);

        $response = $this->jsonRequest('POST', '/api/companies', ['name' => 'ACME'], $auth);

        $this->assertJsonError($response, Response::HTTP_CONFLICT, 'You already have a company with this name.');
    }

    /**
     * @throws JsonException
     */
    public function testLosingATagNameRaceIsAConflict(): void
    {
        $auth = $this->signIn();
        $this->client->disableReboot();
        self::getContainer()->set(CategoryRepository::class, new class(self::getContainer()->get(ManagerRegistry::class)) extends CategoryRepository {
            public function nameExistsInCategory(CategoryEntity $category, string $name, ?TagEntity $except = null): bool
            {
                return false;
            }
        });
        $category = $this->createdId('/api/categories', ['name' => 'Skill'], $auth);
        $this->createdId('/api/categories/'.$category.'/tags', ['name' => 'php'], $auth);

        $response = $this->jsonRequest('POST', '/api/categories/'.$category.'/tags', ['name' => 'PHP'], $auth);

        $this->assertJsonError($response, Response::HTTP_CONFLICT, 'This category already has a tag with this name.');
    }
}
