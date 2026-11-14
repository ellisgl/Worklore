<?php

declare(strict_types=1);

namespace App\Export\Controller;

use App\Export\Application\ExportDocument;
use App\Export\Application\ImportData;
use App\Export\Application\ImportResult;
use App\Shared\Http\OpenApi\Conflict;
use App\Shared\Http\OpenApi\Invalid;
use App\Shared\Http\OpenApi\Item;
use App\Shared\Owner;
use LogicException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Account')]
final class ImportController extends AbstractController
{
    public function __construct(
        // Named autowiring: $commandBus is the command.bus service.
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    /**
     * Restore an export.
     *
     * POST /api/me/import with the JSON file GET /api/me/export gave you, as
     * is. Replays its event history into your account, which must have no
     * records yet: every record and every change comes back, with its
     * original times. Records get new ids. All or nothing.
     *
     * @throws ExceptionInterface
     */
    #[OA\RequestBody(description: 'A file from GET /api/me/export (format version 2)', required: true, content: new OA\JsonContent(type: 'object'))]
    #[Item(ImportResult::class, 'Imported')]
    #[Invalid('the file is not an export this server can import, e.g. "Event 3 in the export refers to a record that is not in the export."')]
    #[Conflict('Your account already has records', 'Imports go into an account with no records. Sign up for a new account (or delete this one\'s records) first.')]
    #[Route('/api/me/import', name: 'api_me_import', methods: ['POST'])]
    public function import(#[CurrentUser] Owner $owner, Request $request): JsonResponse
    {
        $envelope = $this->commandBus->dispatch(new ImportData($owner->ownerId(), ExportDocument::events($request->toArray())));
        $result = $envelope->last(HandledStamp::class)?->getResult();
        if (!$result instanceof ImportResult) {
            throw new LogicException('ImportData returned no result.');
        }

        return $this->json($result);
    }
}
