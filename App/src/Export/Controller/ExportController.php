<?php

declare(strict_types=1);

namespace App\Export\Controller;

use App\Export\DataExport;
use App\Export\DataExporter;
use App\Shared\Http\OpenApi\Item;
use App\Shared\Owner;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use OpenApi\Attributes as OA;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

#[OA\Tag(name: 'Account')]
final class ExportController extends AbstractController
{
    public function __construct(
        private readonly DataExporter $exporter,
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
}
