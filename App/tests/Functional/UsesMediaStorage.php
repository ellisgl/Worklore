<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function array_values;
use function base64_decode;
use function file_put_contents;
use function max;
use function str_repeat;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;

/**
 * For tests that store media: files to upload, a look into media.storage
 * (a local directory per test process in the test env), and emptying it
 * afterwards, since the database rollback doesn't reach it.
 */
trait UsesMediaStorage
{
    /** A 1×1 PNG, 70 bytes */
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * @throws FilesystemException
     */
    protected function tearDown(): void
    {
        $storage = self::mediaStorage();
        foreach ($storage->listContents('') as $item) {
            $item->isDir() ? $storage->deleteDirectory($item->path()) : $storage->delete($item->path());
        }
        parent::tearDown();
    }

    protected static function mediaStorage(): FilesystemOperator
    {
        return self::getContainer()->get('media.storage');
    }

    /**
     * Every stored path, sorted.
     *
     * @return list<string>
     *
     * @throws FilesystemException
     */
    protected static function storedPaths(): array
    {
        return array_values(self::mediaStorage()->listContents('', true)
            ->filter(static fn (StorageAttributes $item): bool => $item->isFile())
            ->sortByPath()
            ->map(static fn (StorageAttributes $item): string => $item->path())
            ->toArray());
    }

    /**
     * A PNG, padded to $size bytes if given.
     */
    protected static function png(string $name = 'pixel.png', int $size = 0): UploadedFile
    {
        $png = (string) base64_decode(self::PNG, true);

        return self::upload($name, $png.str_repeat("\0", max(0, $size - strlen($png))));
    }

    protected static function upload(string $name, string $contents): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, test: true);
    }
}
