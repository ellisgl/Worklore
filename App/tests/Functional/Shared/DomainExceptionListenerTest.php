<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Shared\ConflictException;
use App\Shared\Http\DomainExceptionListener;
use App\Shared\InvalidValueException;
use App\Shared\OwnershipMismatchException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

final class DomainExceptionListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{Throwable, int, string}>
     */
    public static function mappedProvider(): iterable
    {
        yield 'invalid value' => [new InvalidValueException('Company name cannot be blank'), 422, 'Company name cannot be blank'];
        yield 'conflict' => [new class('That name is taken.') extends ConflictException {}, 409, 'That name is taken.'];
        yield 'ownership mismatch hides details' => [new OwnershipMismatchException('The tag belongs to a different user'), 404, 'Not found.'];
    }

    #[DataProvider('mappedProvider')]
    public function testMapsDomainExceptions(Throwable $exception, int $status, string $error): void
    {
        $event = $this->dispatch('/api/companies', $exception);

        self::assertSame($status, $event->getResponse()?->getStatusCode());
        self::assertSame(['error' => $error], json_decode((string) $event->getResponse()->getContent(), true));
    }

    #[DataProvider('mappedProvider')]
    public function testUnwrapsMessengerHandlerFailures(Throwable $exception, int $status, string $error): void
    {
        $wrapped = new HandlerFailedException(new Envelope(new stdClass()), [$exception]);

        $response = $this->dispatch('/api/companies', $wrapped)->getResponse();

        self::assertSame($status, $response?->getStatusCode());
        self::assertSame(['error' => $error], json_decode((string) $response->getContent(), true));
    }

    public function testLeavesOtherExceptionsAlone(): void
    {
        self::assertNull($this->dispatch('/api/companies', new RuntimeException('boom'))->getResponse());
        self::assertNull($this->dispatch('/api/companies', new InvalidArgumentException('a bug, not a domain rule'))->getResponse());
    }

    public function testLeavesNonApiRequestsAlone(): void
    {
        self::assertNull($this->dispatch('/somewhere-else', new InvalidValueException('x'))->getResponse());
    }

    private function dispatch(string $path, Throwable $exception): ExceptionEvent
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create($path), HttpKernelInterface::MAIN_REQUEST, $exception);
        new DomainExceptionListener()($event);

        return $event;
    }
}
