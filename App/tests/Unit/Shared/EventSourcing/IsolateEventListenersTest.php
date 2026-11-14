<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\EventSourcing;

use App\Shared\EventSourcing\Listener\IsolateEventListeners;
use App\Tests\Fixtures\EventSourcing\NoteEdited;
use App\Tests\Fixtures\EventSourcing\NoteId;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\HandledStamp;

use function array_map;

final class IsolateEventListenersTest extends TestCase
{
    /**
     * @throws ExceptionInterface
     */
    public function testAFailingListenerIsLoggedAndTheOthersStillRun(): void
    {
        $log = new TestHandler();
        $failing = new HandlerDescriptor(static fn (NoteEdited $event): never => throw new RuntimeException('Mail server down'), ['alias' => 'mailer']);
        $working = new HandlerDescriptor(static fn (NoteEdited $event): string => 'done', ['alias' => 'counter']);
        $bus = new MessageBus([
            new IsolateEventListeners(new Logger('domain', [$log])),
            new HandleMessageMiddleware(new HandlersLocator([NoteEdited::class => [$failing, $working]])),
        ]);

        $envelope = $bus->dispatch(new NoteEdited(NoteId::generate(), 'Hello'));

        self::assertSame(['done'], array_map(static fn (HandledStamp $handled): mixed => $handled->getResult(), $envelope->all(HandledStamp::class)));
        self::assertCount(1, $log->getRecords());
        $record = $log->getRecords()[0];
        self::assertSame(Level::Error, $record->level);
        self::assertSame(NoteEdited::class, $record->context['event']);
        self::assertSame('Mail server down', $record->context['message']);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testPassesEventsThroughWhenListenersSucceed(): void
    {
        $log = new TestHandler();
        $bus = new MessageBus([
            new IsolateEventListeners(new Logger('domain', [$log])),
            new HandleMessageMiddleware(new HandlersLocator([NoteEdited::class => [static fn (NoteEdited $event): string => 'done']])),
        ]);

        $bus->dispatch(new NoteEdited(NoteId::generate(), 'Hello'));

        self::assertSame([], $log->getRecords());
    }
}
