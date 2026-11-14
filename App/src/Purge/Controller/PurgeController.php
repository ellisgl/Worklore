<?php

declare(strict_types=1);

namespace App\Purge\Controller;

use App\Purge\Application\PurgeRecord;
use App\Purge\Application\RecordNotFound;
use App\Purge\PurgePlanner;
use App\Purge\PurgeReport;
use App\Purge\PurgeTarget;
use App\Shared\Http\OpenApi\Item;
use App\Shared\Http\OpenApi\NotFound;
use App\Shared\Owner;
use App\Shared\Security\OnlyWhileImpersonating;
use Doctrine\DBAL\Exception;
use LogicException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Admin')]
final class PurgeController extends AbstractController
{
    public function __construct(
        // Named autowiring: $commandBus is the command.bus service.
        private readonly MessageBusInterface $commandBus,
        private readonly PurgePlanner $planner,
    ) {
    }

    /**
     * Purge a record for good.
     *
     * POST /api/purge/{type}/{id}[?dry_run=1], with an impersonation token
     * (POST /api/admin/users/{id}/impersonate) for the record's owner.
     * Erases the record, everything under it (a company's products,
     * positions, projects, journal entries and their files; a category's
     * tags), and all their history. Records that keep living lose any purged
     * tag. This can't be undone; dry_run=1 only reports what would go.
     *
     * @throws Exception
     * @throws ExceptionInterface
     */
    #[OA\Parameter(name: 'type', in: 'path', schema: new OA\Schema(type: 'string', enum: ['companies', 'products', 'projects', 'positions', 'journal-entries', 'categories', 'tags']))]
    #[Item(PurgeReport::class, 'Purged (or, for a dry run, what would be)')]
    #[OA\Response(response: 403, description: 'Not an admin impersonating the owner', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[NotFound('Not found.')]
    #[OnlyWhileImpersonating]
    #[Route('/api/purge/{type}/{id}', name: 'api_purge', requirements: ['type' => new EnumRequirement(PurgeTarget::class), 'id' => Requirement::UUID], methods: ['POST'])]
    public function purge(
        #[CurrentUser] Owner $owner,
        PurgeTarget $type,
        string $id,
        #[MapQueryParameter(name: 'dry_run')] bool $dryRun = false,
    ): JsonResponse {
        if ($dryRun) {
            $plan = $this->planner->plan($owner->ownerId(), $type, $id) ?? throw new RecordNotFound();

            return $this->json($plan->report(dryRun: true));
        }

        $report = $this->commandBus->dispatch(new PurgeRecord($owner->ownerId(), $type, $id))->last(HandledStamp::class)?->getResult();
        if (!$report instanceof PurgeReport) {
            throw new LogicException('PurgeRecord returned no report.');
        }

        return $this->json($report);
    }
}
