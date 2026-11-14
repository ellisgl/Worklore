<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Identity\Application\SetStorageQuota;
use App\Shared\CommandBus;
use App\Shared\UserId;
use App\Tests\Functional\ApiTestCase;
use App\Tests\Functional\UsesMediaStorage;
use JsonException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ZipArchive;

use function array_column;
use function file_put_contents;
use function hash;
use function is_array;
use function json_decode;
use function json_encode;
use function str_repeat;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;

use const JSON_THROW_ON_ERROR;

/**
 * The archive export (zip with media) and importing it.
 */
final class ArchiveImportTest extends ApiTestCase
{
    use UsesMediaStorage;

    private const string PDF = "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n";

    /**
     * @throws FilesystemException
     * @throws JsonException
     */
    public function testAnArchiveRestoresRecordsAndTheirFiles(): void
    {
        self::mockTime('2026-03-04 05:06:07 UTC');
        $original = $this->signIn('original@example.com');
        $entry = $this->entryFor($original);
        $png = $this->attachedId($entry, self::png('whiteboard.png'), $original);
        $removed = $this->attachedId($entry, self::upload('draft.pdf', self::PDF), $original);
        $pdf = $this->attachedId($entry, self::upload('notes.pdf', self::PDF), $original);
        $this->jsonRequest('DELETE', '/api/journal-entries/'.$entry.'/media/'.$removed, headers: $original);

        [$zip, $contents] = $this->archive($original);
        self::assertSame(['export.json', 'media/'.$png, 'media/'.$pdf], $contents, 'Only files still attached');
        $exported = self::document($zip)['media'] ?? null;
        self::assertIsArray($exported);
        self::assertSame([$png, $pdf], array_column($exported, 'id'));

        $restored = $this->signIn('restored@example.com');
        $response = $this->import($zip, $restored);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['records' => 4, 'events' => 8, 'files' => 2], $this->decode($response));
        $restoredEntry = $this->getItems('/api/journal-entries', $restored)[0] ?? null;
        self::assertIsArray($restoredEntry);
        $media = $restoredEntry['media'] ?? null;
        self::assertIsArray($media);
        self::assertSame(['whiteboard.png', 'notes.pdf'], array_column($media, 'filename'));
        self::assertNotContains($png, array_column($media, 'id'), 'New media ids');
        self::assertSame(70 + strlen(self::PDF), $this->getJson('/api/me/storage', $restored)['used'] ?? null);

        $second = $media[1] ?? null;
        self::assertIsArray($second);
        $id = $second['id'] ?? null;
        $entryId = $restoredEntry['id'] ?? null;
        self::assertIsString($id);
        self::assertIsString($entryId);
        $this->jsonRequest('GET', '/api/journal-entries/'.$entryId.'/media/'.$id, headers: $restored);
        self::assertSame(self::PDF, $this->client->getInternalResponse()->getContent(), 'Same bytes');
        self::assertCount(4, self::storedPaths(), 'Two files per account');
    }

    /**
     * @throws FilesystemException
     * @throws JsonException
     */
    public function testRefusesArchivesThatDoNotAddUp(): void
    {
        $original = $this->signIn('original@example.com');
        $entry = $this->entryFor($original);
        $png = $this->attachedId($entry, self::png(), $original);
        [$zip] = $this->archive($original);
        $document = self::document($zip);
        $mine = $this->signIn();

        $cases = [
            'json only' => [null, 'This export has media files. Import the archive (GET /api/me/export/archive) instead of the JSON.'],
            'not a zip' => [self::file('not a zip'), 'The file is not a zip archive.'],
            'no export.json' => [self::zip(['media/'.$png => 'x']), 'The archive has no export.json.'],
            'missing file' => [self::zip(['export.json' => json_encode($document, JSON_THROW_ON_ERROR)]), 'The archive is missing media/'.$png.'.'],
            'changed bytes' => [self::zip(['export.json' => json_encode($document, JSON_THROW_ON_ERROR), 'media/'.$png => str_repeat('x', 70)]), 'media/'.$png.' in the archive doesn\'t match its size or checksum.'],
            'other type' => [
                self::zip(['export.json' => self::withMedia($document, strlen(self::PDF), hash('sha256', self::PDF)), 'media/'.$png => self::PDF]),
                'media/'.$png.' in the archive is application/pdf, not the image/png its record says.',
            ],
        ];
        foreach ($cases as $case => [$file, $error]) {
            $response = null === $file
                ? $this->jsonRequest('POST', '/api/me/import', self::object($document), $mine)
                : $this->import($file, $mine);
            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), $case.': '.$response->getContent());
            self::assertSame(['error' => $error], $this->decode($response), $case);
        }

        self::assertSame([], $this->getItems('/api/journal-entries', $mine), 'Nothing imported');
        self::assertCount(1, self::storedPaths(), 'Only the original file');
    }

    /**
     * @throws FilesystemException
     * @throws JsonException
     * @throws Throwable
     */
    public function testTheFilesMustFitInTheImportersQuota(): void
    {
        $original = $this->signIn('original@example.com');
        $originalId = $this->getJson('/api/me', $original)['id'] ?? null;
        self::assertIsString($originalId);
        self::getContainer()->get(CommandBus::class)->dispatch(new SetStorageQuota(UserId::fromString($originalId), 10 * 1024 ** 2));
        $entry = $this->entryFor($original);
        for ($i = 0; $i < 4; ++$i) {
            $this->attachedId($entry, self::png('big'.$i.'.png', 1024 ** 2 - 1024), $original);
        }
        [$zip] = $this->archive($original);
        $mine = $this->signIn();

        $this->assertJsonError($this->import($zip, $mine), Response::HTTP_CONFLICT, 'Not enough storage left: the export\'s files are 4 MB, and you have 3 MB of your 3 MB left.');
        self::assertSame([], $this->getItems('/api/journal-entries', $mine));
        self::assertCount(4, self::storedPaths());
    }

    /**
     * @param array<string, string> $auth
     *
     * @return array{UploadedFile, list<string>} the zip, and the names in it
     *
     * @throws JsonException
     */
    private function archive(array $auth): array
    {
        $this->jsonRequest('GET', '/api/me/export/archive', headers: $auth);
        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/zip', $response->headers->get('Content-Type'));
        self::assertStringStartsWith('attachment; filename=worklore-export-', (string) $response->headers->get('Content-Disposition'));

        $file = self::file($this->client->getInternalResponse()->getContent());
        $zip = new ZipArchive();
        self::assertTrue($zip->open($file->getPathname()));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = (string) $zip->getNameIndex($i);
        }
        $zip->close();

        return [$file, $names];
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private static function document(UploadedFile $archive): array
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($archive->getPathname()));
        $json = $zip->getFromName('export.json');
        $zip->close();
        self::assertIsString($json);
        $document = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($document);

        return $document;
    }

    /**
     * export.json with the (only) media file's size and checksum replaced.
     *
     * @param array<array-key, mixed> $document
     *
     * @throws JsonException
     */
    private static function withMedia(array $document, int $size, string $sha256): string
    {
        $events = $document['events'] ?? null;
        self::assertIsArray($events);
        foreach ($events as $i => $event) {
            $data = is_array($event) ? $event['data'] ?? null : null;
            if (is_array($event) && is_array($data) && 'journal_entry.media_attached' === ($event['type'] ?? null)) {
                $data['size'] = $size;
                $data['sha256'] = $sha256;
                $event['data'] = $data;
                $events[$i] = $event;
            }
        }
        $document['events'] = $events;

        return json_encode($document, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, string> $entries name => contents
     */
    private static function zip(array $entries): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::OVERWRITE));
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return new UploadedFile($path, 'export.zip', test: true);
    }

    private static function file(string $contents): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'export.zip', test: true);
    }

    /**
     * @param array<array-key, mixed> $document
     *
     * @return array<string, mixed>
     */
    private static function object(array $document): array
    {
        $object = [];
        foreach ($document as $key => $value) {
            $object[(string) $key] = $value;
        }

        return $object;
    }

    /**
     * @param array<string, string> $auth
     */
    private function import(UploadedFile $archive, array $auth): Response
    {
        $this->client->request('POST', '/api/me/import', files: ['file' => $archive], server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => $auth['Authorization']]);

        return $this->client->getResponse();
    }

    /**
     * @param array<string, string> $auth
     *
     * @throws JsonException
     */
    private function attachedId(string $entry, UploadedFile $file, array $auth): string
    {
        $this->client->request('POST', '/api/journal-entries/'.$entry.'/media', files: ['file' => $file], server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => $auth['Authorization']]);
        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $id = $this->decode($response)['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    /**
     * @param array<string, string> $auth
     *
     * @throws JsonException
     */
    private function entryFor(array $auth): string
    {
        $company = $this->createdId('/api/companies', ['name' => 'Acme'], $auth);
        $product = $this->createdId('/api/companies/'.$company.'/products', ['name' => 'Widget'], $auth);
        $project = $this->createdId('/api/products/'.$product.'/projects', ['name' => 'Launch'], $auth);

        return $this->createdId('/api/projects/'.$project.'/journal-entries', ['title' => 'Kickoff', 'entry' => 'Files attached.'], $auth);
    }
}
