<?php

declare(strict_types=1);

namespace App\Company;

use App\Shared\EventSourcing\AggregateStore;
use App\Shared\EventSourcing\ConcurrentModification;
use App\Shared\UserId;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Companys, loaded from and saved to their event streams.
 */
final readonly class CompanyRepository
{
    public function __construct(private AggregateStore $store)
    {
    }

    /**
     * The company, if it exists and belongs to the owner; null otherwise, so an
     * endpoint answers 404 instead of revealing someone else's record.
     * Archived ones are returned too.
     *
     * @throws Exception
     */
    public function findForOwner(CompanyId $id, UserId $ownerId): ?Company
    {
        $history = $this->store->history(CompanyEvents::COMPANY, $id);
        if ([] === $history) {
            return null;
        }
        $company = Company::fromHistory($history);

        return $company->ownerId()->equals($ownerId) ? $company : null;
    }

    /**
     * @throws ConcurrentModification
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function save(Company $company): void
    {
        $this->store->save(CompanyEvents::COMPANY, $company);
    }
}
