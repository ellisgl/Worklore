<?php

declare(strict_types=1);

namespace App\Tests\Functional\Journal;

use App\Journal\Application\AttachMedia;
use App\Journal\JournalEntry;
use App\Journal\JournalEntryId;
use App\Journal\JournalEntryRepository;
use App\Journal\MediaId;
use App\Shared\CommandBus;
use App\Tests\Functional\CreatesRecords;
use App\Tests\Functional\UsesMediaStorage;
use DateTimeImmutable;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

use function hash_file;

final class PruneMediaCommandTest extends KernelTestCase
{
    use CreatesRecords;
    use UsesMediaStorage;

    /**
     * @throws FilesystemException
     * @throws Throwable
     */
    public function testDeletesOnlyFilesNoEntryRefersTo(): void
    {
        self::bootKernel();
        $owner = $this->owner();
        $project = $this->project($this->product($this->company($owner)));
        $entry = JournalEntry::write(JournalEntryId::generate(), $project->id(), $owner->id, 'Kickoff', 'Body', [], new DateTimeImmutable());
        self::getContainer()->get(JournalEntryRepository::class)->save($entry);

        $file = self::png();
        $attached = MediaId::generate();
        self::getContainer()->get(CommandBus::class)->dispatch(new AttachMedia(
            $entry->id(), $owner->id, $attached, $file->getPathname(), 'pixel.png', 'image/png', (int) $file->getSize(), (string) hash_file('sha256', $file->getPathname()),
        ));
        $orphan = $owner->id->toRfc4122().'/'.MediaId::generate()->toRfc4122();
        self::mediaStorage()->write($orphan, 'left behind');
        self::mediaStorage()->write('README.txt', 'not media');

        $tester = $this->prune(['--dry-run' => true, '--grace-minutes' => 0]);
        self::assertStringContainsString('Found 1 orphaned file(s); nothing deleted', $tester->getDisplay());
        self::assertCount(3, self::storedPaths());

        self::assertStringContainsString('Deleted 0 orphaned file(s)', $this->prune([])->getDisplay(), 'New files are left alone');

        $tester = $this->prune(['--grace-minutes' => 0]);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Deleted 1 orphaned file(s)', $tester->getDisplay());
        self::assertStringContainsString('Skipped README.txt', $tester->getDisplay());
        self::assertSame([$owner->id->toRfc4122().'/'.$attached->toRfc4122(), 'README.txt'], self::storedPaths());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function prune(array $options): CommandTester
    {
        self::assertNotNull(self::$kernel);
        $tester = new CommandTester(new Application(self::$kernel)->find('app:media:prune'));
        $tester->execute($options);

        return $tester;
    }
}
