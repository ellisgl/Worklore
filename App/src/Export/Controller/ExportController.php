<?php

declare(strict_types=1);

namespace App\Export\Controller;

use App\Export\ArchiveExporter;
use App\Export\DataExport;
use App\Export\DataExporter;
use App\Shared\Http\OpenApi\Item;
use App\Shared\Owner;
use App\Shared\Security\NotWhileImpersonating;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use OpenApi\Attributes as OA;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

use function fclose;
use function fopen;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

#[OA\Tag(name: 'Account')]
final class ExportController extends AbstractController
{
    public function __construct(
        private readonly DataExporter $exporter,
        private readonly ArchiveExporter $archives,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Download all your data.
     *
     * GET /api/me/export: everything you own, archived records included, as
     * a JSON file (worklore-export-YYYY-MM-DD.json). One list per record type,
     * linked by ids. No passwords or tokens.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    #[Item(DataExport::class, 'The export, as a file download')]
    #[NotWhileImpersonating]
    #[Route('/api/me/export', name: 'api_me_export', methods: ['GET'])]
    public function export(#[CurrentUser] Owner $owner): JsonResponse
    {
        $response = $this->json($this->exporter->export($owner->ownerId()));
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'worklore-export-'.$this->clock->now()->format('Y-m-d').'.json',
        ));

        return $response;
    }

    /**
     * Download all your data, files included.
     *
     * GET /api/me/export/archive: a zip (worklore-export-YYYY-MM-DD.zip)
     * holding export.json (the same document as GET /api/me/export) and
     * media/{id}, the bytes of each file attached to a journal entry. This
     * is the export to keep as a backup: POST /api/me/import restores it.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     */
    #[OA\Response(response: 200, description: 'The archive, as a file download', content: new OA\MediaType(mediaType: 'application/zip', schema: new OA\Schema(type: 'string', format: 'binary')))]
    #[NotWhileImpersonating]
    #[Route('/api/me/export/archive', name: 'api_me_export_archive', methods: ['GET'])]
    public function archive(#[CurrentUser] Owner $owner): StreamedResponse
    {
        // Read before streaming starts, so a database error is still a clean 500
        $export = $this->exporter->export($owner->ownerId());
        $ownerId = $owner->ownerId();

        $response = new StreamedResponse(function () use ($export, $ownerId): void {
            $output = fopen('php://output', 'wb');
            if (false === $output) {
                throw new RuntimeException('Cannot write the response.');
            }
            $this->archives->write($export, $ownerId, $output);
            fclose($output);
        });
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'worklore-export-'.$this->clock->now()->format('Y-m-d').'.zip',
        ));
        $response->setPrivate();

        return $response;
    }
}
