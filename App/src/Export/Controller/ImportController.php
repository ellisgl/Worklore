<?php

declare(strict_types=1);

namespace App\Export\Controller;

use App\Export\Application\ExportArchive;
use App\Export\Application\ExportDocument;
use App\Export\Application\ImportData;
use App\Export\Application\ImportResult;
use App\Shared\Http\OpenApi\Conflict;
use App\Shared\Http\OpenApi\Invalid;
use App\Shared\Http\OpenApi\Item;
use App\Shared\InvalidValueException;
use App\Shared\Owner;
use LogicException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
     * POST /api/me/import with either the zip from GET /api/me/export/archive
     * (multipart/form-data, in a field named "file"), or the JSON from
     * GET /api/me/export as the request body (only if it has no media).
     * Replays its event history into your account, which must have no
     * records yet: every record, change and file comes back, with its
     * original times. Records get new ids. All or nothing.
     *
     * @throws ExceptionInterface
     */
    #[OA\RequestBody(description: 'An export (format version 2)', required: true, content: [
        new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(
            required: ['file'],
            properties: [new OA\Property(property: 'file', description: 'The zip from GET /api/me/export/archive', type: 'string', format: 'binary')],
        )),
        new OA\JsonContent(description: 'The JSON from GET /api/me/export', type: 'object'),
    ])]
    #[Item(ImportResult::class, 'Imported')]
    #[Invalid('the file is not an export this server can import, e.g. "Event 3 in the export refers to a record that is not in the export."')]
    #[Conflict('Your account already has records', 'Imports go into an account with no records. Sign up for a new account (or delete this one\'s records) first.')]
    #[Route('/api/me/import', name: 'api_me_import', methods: ['POST'])]
    public function import(#[CurrentUser] Owner $owner, Request $request): JsonResponse
    {
        $envelope = $this->commandBus->dispatch(self::command($owner, $request));
        $result = $envelope->last(HandledStamp::class)?->getResult();
        if (!$result instanceof ImportResult) {
            throw new LogicException('ImportData returned no result.');
        }

        return $this->json($result);
    }

    /**
     * @throws InvalidValueException
     */
    private static function command(Owner $owner, Request $request): ImportData
    {
        if ('form' !== $request->getContentTypeFormat()) {
            return new ImportData($owner->ownerId(), ExportDocument::events($request->toArray()));
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new InvalidValueException('Send the archive as multipart/form-data, in a field named "file".');
        }
        $archive = ExportArchive::open($file->getPathname());

        return new ImportData($owner->ownerId(), ExportDocument::events($archive->document()), $archive);
    }
}
