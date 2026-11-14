<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Identity\Application\DeleteUser;
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

use function array_column;
use function array_intersect_key;
use function base64_decode;
use function hash;
use function str_replace;
use function strtoupper;

final class MediaControllerTest extends ApiTestCase
{
    use UsesMediaStorage;

    /** @var array<string, string> */
    private array $auth;
    private string $owner;
    private string $entry;

    /**
     * @throws JsonException
     */
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime('2026-03-04 05:06:07 UTC');
        $this->auth = $this->signIn();
        $owner = $this->getJson('/api/me', $this->auth)['id'] ?? null;
        self::assertIsString($owner);
        $this->owner = $owner;
        $this->entry = $this->entryFor($this->auth);
    }

    /**
     * @throws FilesystemException
     * @throws JsonException
     */
    public function testAttachesAFileAndServesItBack(): void
    {
        $response = $this->attach($this->entry, self::png('Whiteboard: sprint 1.png'));

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $png = (string) base64_decode(self::PNG, true);
        $media = $this->decode($response);
        $id = $media['id'] ?? null;
        self::assertIsString($id);
        self::assertSame(
            ['id' => $id, 'filename' => 'Whiteboard: sprint 1.png', 'media_type' => 'image/png', 'size' => 70, 'sha256' => hash('sha256', $png), 'created_at' => '2026-03-04T05:06:07+00:00'],
            $media,
        );
        self::assertSame([$this->owner.'/'.$id], self::storedPaths(), 'Stored under owner/media id');
        self::assertSame([$media], $this->getJson('/api/journal-entries/'.$this->entry, $this->auth)['media'] ?? null, 'The entry lists it');
        self::assertSame(70, $this->getJson('/api/me/storage', $this->auth)['used'] ?? null);

        $this->client->request('GET', $this->mediaUri($this->entry, $id), server: self::server($this->auth));
        $download = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $download->getStatusCode());
        self::assertSame($png, $this->client->getInternalResponse()->getContent());
        self::assertSame('image/png', $download->headers->get('Content-Type'));
        self::assertSame('70', $download->headers->get('Content-Length'));
        self::assertSame('inline; filename="Whiteboard: sprint 1.png"', $download->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $download->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('sandbox', (string) $download->headers->get('Content-Security-Policy'));
        $etag = (string) $download->headers->get('ETag');
        self::assertSame('"'.hash('sha256', $png).'"', $etag);

        $this->client->request('GET', $this->mediaUri($this->entry, $id), server: self::server($this->auth + ['If-None-Match' => $etag]));
        self::assertSame(Response::HTTP_NOT_MODIFIED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testANonAsciiFilenameGetsAnAsciiFallback(): void
    {
        $id = $this->attachedId($this->entry, self::png('Größe "report".png'));
        $this->client->request('GET', $this->mediaUri($this->entry, $id), server: self::server($this->auth));
        self::assertSame(
            'inline; filename="Gr__e _report_.png"; filename*=utf-8\'\'Gr%C3%B6%C3%9Fe%20%22report%22.png',
            $this->client->getResponse()->headers->get('Content-Disposition'),
        );
    }

    /**
     * @throws JsonException
     */
    public function testAPdfIsDetectedAndServedInline(): void
    {
        $id = $this->attachedId($this->entry, self::upload('notes.pdf', "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n"));
        $this->client->request('GET', $this->mediaUri($this->entry, $id), server: self::server($this->auth));
        self::assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame('inline; filename=notes.pdf', $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    /**
     * @throws FilesystemException
     * @throws JsonException
     */
    public function testRejectsWhatCannotBeAttached(): void
    {
        $this->assertJsonError($this->jsonRequest('POST', '/api/journal-entries/'.$this->entry.'/media', [], $this->auth), Response::HTTP_UNPROCESSABLE_ENTITY, 'Send the file as multipart/form-data, in a field named "file".');
        $this->assertJsonError(
            $this->attach($this->entry, self::upload('script.png', "#!/bin/sh\necho pwned\n")),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Files of type text/x-shellscript can\'t be attached. Allowed: image/jpeg, image/png, image/gif, image/webp, application/pdf, audio/mpeg, audio/ogg, audio/wav, audio/mp4, video/mp4, video/webm, video/quicktime.',
        );
        $this->assertJsonError($this->attach($this->entry, self::png(size: 1024 ** 2 + 1)), Response::HTTP_UNPROCESSABLE_ENTITY, 'The file is larger than the 1 MB limit.');
        self::assertSame([], self::storedPaths());
    }

    /**
     * @throws FilesystemException
     * @throws JsonException
     * @throws Throwable
     */
    public function testAFileMustFitInWhatIsLeftOfTheQuota(): void
    {
        self::getContainer()->get(CommandBus::class)->dispatch(new SetStorageQuota(UserId::fromString($this->owner), 100));
        $this->attachedId($this->entry, self::png());

        $this->assertJsonError($this->attach($this->entry, self::png()), Response::HTTP_CONFLICT, 'Not enough storage left: the file is 70 bytes, and you have 30 bytes of your 100 bytes left.');
        self::assertCount(1, self::storedPaths(), 'Nothing stored');
        self::assertSame(['used' => 70, 'limit' => 100], array_intersect_key($this->getJson('/api/me/storage', $this->auth), ['used' => 0, 'limit' => 0]));
    }

    /**
     * @throws FilesystemException
     * @throws JsonException
     */
    public function testRemovingAFileDeletesItAndFreesItsSpace(): void
    {
        $id = $this->attachedId($this->entry, self::png());
        $kept = $this->attachedId($this->entry, self::png('kept.png'));

        $response = $this->jsonRequest('DELETE', $this->mediaUri($this->entry, $id), headers: $this->auth);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([$this->owner.'/'.$kept], self::storedPaths());
        $media = $this->getJson('/api/journal-entries/'.$this->entry, $this->auth)['media'] ?? null;
        self::assertIsArray($media);
        self::assertSame([$kept], array_column($media, 'id'));
        self::assertSame(70, $this->getJson('/api/me/storage', $this->auth)['used'] ?? null);
        $this->assertJsonError($this->jsonRequest('GET', $this->mediaUri($this->entry, $id), headers: $this->auth), Response::HTTP_NOT_FOUND, 'Media not found.');
        $this->assertJsonError($this->jsonRequest('DELETE', $this->mediaUri($this->entry, $id), headers: $this->auth), Response::HTTP_NOT_FOUND, 'Media not found.');

        $history = $this->getItems('/api/journal-entries/'.$this->entry.'/history', $this->auth);
        self::assertSame(['journal_entry.written', 'journal_entry.media_attached', 'journal_entry.media_attached', 'journal_entry.media_removed'], array_column($history, 'type'));
    }

    /**
     * @throws FilesystemException
     * @throws JsonException
     */
    public function testOtherUsersCannotSeeOrTouchYourFiles(): void
    {
        $id = $this->attachedId($this->entry, self::png());
        $other = $this->signIn('other@example.com');

        $this->assertJsonError($this->jsonRequest('GET', $this->mediaUri($this->entry, $id), headers: $other), Response::HTTP_NOT_FOUND);
        $this->assertJsonError($this->jsonRequest('DELETE', $this->mediaUri($this->entry, $id), headers: $other), Response::HTTP_NOT_FOUND);
        $this->assertJsonError($this->attach($this->entry, self::png(), $other), Response::HTTP_NOT_FOUND, 'Journal entry not found.');
        $this->assertJsonError($this->jsonRequest('GET', $this->mediaUri($this->entryFor($other), $id), headers: $other), Response::HTTP_NOT_FOUND, 'Media not found.');
        self::assertSame([$this->owner.'/'.$id], self::storedPaths());
    }

    /**
     * @throws FilesystemException
     * @throws JsonException
     * @throws Throwable
     */
    public function testDeletingAUserDeletesTheirFiles(): void
    {
        $this->attachedId($this->entry, self::png());
        $other = $this->signIn('other@example.com');
        $theirs = $this->attachedId($this->entryFor($other), self::png(), $other);
        $otherId = $this->getJson('/api/me', $other)['id'] ?? null;
        self::assertIsString($otherId);

        self::getContainer()->get(CommandBus::class)->dispatch(new DeleteUser(UserId::fromString($this->owner)));

        self::assertSame([$otherId.'/'.$theirs], self::storedPaths());
    }

    /**
     * @throws JsonException
     */
    public function testStorageShowsTheLimits(): void
    {
        self::assertSame(
            ['used' => 0, 'limit' => 3 * 1024 ** 2, 'max_file_size' => 1024 ** 2],
            array_intersect_key($this->getJson('/api/me/storage', $this->auth), ['used' => 0, 'limit' => 0, 'max_file_size' => 0]),
        );
    }

    /**
     * @param array<string, string>|null $auth
     */
    private function attach(string $entry, UploadedFile $file, ?array $auth = null): Response
    {
        $this->client->request('POST', '/api/journal-entries/'.$entry.'/media', files: ['file' => $file], server: self::server($auth ?? $this->auth));

        return $this->client->getResponse();
    }

    /**
     * @param array<string, string>|null $auth
     *
     * @throws JsonException
     */
    private function attachedId(string $entry, UploadedFile $file, ?array $auth = null): string
    {
        $response = $this->attach($entry, $file, $auth);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $id = $this->decode($response)['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    private function mediaUri(string $entry, string $media): string
    {
        return '/api/journal-entries/'.$entry.'/media/'.$media;
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

        return $this->createdId('/api/projects/'.$project.'/journal-entries', ['title' => 'Kickoff', 'entry' => 'Whiteboard attached.'], $auth);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private static function server(array $headers): array
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $server;
    }
}
