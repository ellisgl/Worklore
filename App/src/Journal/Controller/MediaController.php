<?php

declare(strict_types=1);

namespace App\Journal\Controller;

use App\Journal\Application\AttachMedia;
use App\Journal\Application\RemoveMedia;
use App\Journal\JournalEntryId;
use App\Journal\Media\MediaRules;
use App\Journal\Media\MediaStorage;
use App\Journal\MediaId;
use App\Journal\MediaNotFound;
use App\Journal\Query\MediaQueries;
use App\Journal\Query\MediaSummary;
use App\Journal\Query\StorageUsage;
use App\Shared\Http\OpenApi\Conflict;
use App\Shared\Http\OpenApi\Invalid;
use App\Shared\Http\OpenApi\Item;
use App\Shared\Http\OpenApi\NoContent;
use App\Shared\Http\OpenApi\NotFound;
use App\Shared\InvalidValueException;
use App\Shared\Owner;
use App\Shared\StorageQuotas;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

use function fclose;
use function fpassthru;
use function hash_file;
use function is_string;
use function preg_replace;
use function str_starts_with;

use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;

/**
 * Files attached to journal entries. Uploads are multipart/form-data with
 * the file in a field named "file".
 */
#[OA\Tag(name: 'Journal entries')]
#[Route('/api', name: 'api_journal_media_')]
final class MediaController extends AbstractController
{
    /** Shown in the browser when opened directly; everything else downloads */
    private const array INLINE = ['image/', 'audio/', 'video/', 'application/pdf'];

    public function __construct(
        // Named autowiring: $commandBus is the command.bus service.
        private readonly MessageBusInterface $commandBus,
        private readonly MediaQueries $media,
        private readonly MediaRules $rules,
        private readonly MediaStorage $storage,
        private readonly StorageQuotas $quotas,
    ) {
    }

    /**
     * Attach a file to a journal entry.
     *
     * POST /api/journal-entries/{id}/media, multipart/form-data with the
     * file in "file" → 201 with the file's details. The type is detected
     * from the content; it and the size must be allowed (GET /api/me/storage),
     * and the file must fit in what's left of your storage.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws ExceptionInterface
     * @throws InvalidValueException
     */
    #[OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(
        required: ['file'],
        properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')],
    )))]
    #[OA\Response(response: 201, description: 'Attached', content: new OA\JsonContent(ref: new Model(type: MediaSummary::class)))]
    #[Invalid('no file, a file too large, or of a type that isn\'t allowed')]
    #[NotFound('Journal entry not found.')]
    #[Conflict('Not enough storage left', 'Not enough storage left: the file is 3 MB, and you have 1 MB of your 1 GB left.')]
    #[Route('/journal-entries/{id}/media', name: 'attach', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function attach(#[CurrentUser] Owner $owner, string $id, Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new InvalidValueException('Send the file as multipart/form-data, in a field named "file".');
        }
        if (!$file->isValid()) {
            throw match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $this->rules->tooLarge(),
                default => new InvalidValueException('The upload didn\'t arrive complete; try again.'),
            };
        }
        $path = $file->getPathname();
        $size = (int) $file->getSize();
        $mediaType = $file->getMimeType() ?? 'application/octet-stream';
        $this->rules->check($size, $mediaType);
        $sha256 = hash_file('sha256', $path);
        if (!is_string($sha256)) {
            throw new InvalidValueException('The upload can\'t be read; try again.');
        }

        $entryId = JournalEntryId::fromString($id);
        $mediaId = MediaId::generate();
        $this->commandBus->dispatch(new AttachMedia($entryId, $owner->ownerId(), $mediaId, $path, $file->getClientOriginalName(), $mediaType, $size, $sha256));

        return $this->json($this->media->find($owner->ownerId(), $entryId, $mediaId), Response::HTTP_CREATED);
    }

    /**
     * Download a file attached to a journal entry.
     *
     * GET /api/journal-entries/{id}/media/{mediaId}: the bytes, with their
     * type. Images, audio, video and PDFs are served inline, anything else
     * as a download. ETag is the SHA-256, so If-None-Match gets a 304.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws FilesystemException
     * @throws MediaNotFound
     */
    #[OA\Response(response: 200, description: 'The file', content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: 'string', format: 'binary')))]
    #[OA\Response(response: 304, description: 'Not modified (If-None-Match matched)')]
    #[NotFound('Media not found.')]
    #[Route('/journal-entries/{id}/media/{mediaId}', name: 'download', requirements: ['id' => Requirement::UUID, 'mediaId' => Requirement::UUID], methods: ['GET'])]
    public function download(#[CurrentUser] Owner $owner, string $id, string $mediaId, Request $request): Response
    {
        $media = $this->media->find($owner->ownerId(), JournalEntryId::fromString($id), MediaId::fromString($mediaId))
            ?? throw new MediaNotFound();

        $response = new StreamedResponse();
        $response->setPrivate();
        $response->setEtag($media->sha256);
        $response->setLastModified($media->createdAt);
        if ($response->isNotModified($request)) {
            return $response;
        }

        // Opened before the headers go out, so a storage failure is still a clean 500
        $stream = $this->storage->read($owner->ownerId(), MediaId::fromString($media->id));
        $response->setCallback(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        });

        $response->headers->add([
            'Content-Type' => $media->mediaType,
            'Content-Length' => (string) $media->size,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                self::inline($media->mediaType) ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $media->filename,
                self::asciiFallback($media->filename),
            ),
            // Never let a browser treat an upload as a page or script
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);

        return $response;
    }

    /**
     * Remove a file from a journal entry.
     *
     * DELETE /api/journal-entries/{id}/media/{mediaId} → 204. The file is
     * deleted and stops counting towards your storage.
     *
     * @throws ExceptionInterface
     */
    #[NoContent('Removed')]
    #[NotFound('Journal entry not found.', 'Media not found.')]
    #[Route('/journal-entries/{id}/media/{mediaId}', name: 'remove', requirements: ['id' => Requirement::UUID, 'mediaId' => Requirement::UUID], methods: ['DELETE'])]
    public function remove(#[CurrentUser] Owner $owner, string $id, string $mediaId): Response
    {
        $this->commandBus->dispatch(new RemoveMedia(JournalEntryId::fromString($id), $owner->ownerId(), MediaId::fromString($mediaId)));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * See your storage use and limits.
     *
     * GET /api/me/storage: bytes used and allowed, the largest file you can
     * upload, and the types you can attach.
     *
     * @throws Exception
     */
    #[OA\Tag(name: 'Account')]
    #[Item(StorageUsage::class, 'Your storage')]
    #[Route('/me/storage', name: 'storage', methods: ['GET'])]
    public function storage(#[CurrentUser] Owner $owner): JsonResponse
    {
        return $this->json(new StorageUsage(
            used: $this->media->usedBy($owner->ownerId()),
            limit: $this->quotas->limitFor($owner->ownerId()),
            maxFileSize: $this->rules->maxFileSize,
            allowedTypes: $this->rules->allowedTypes,
        ));
    }

    private static function inline(string $mediaType): bool
    {
        foreach (self::INLINE as $prefix) {
            if (str_starts_with($mediaType, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function asciiFallback(string $filename): string
    {
        return (string) preg_replace('/[^\x20-\x7e]|[%\/\\\\"]/u', '_', $filename);
    }
}
