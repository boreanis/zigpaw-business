<?php

namespace Tests\Feature;

use Fiber;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Route;
use SessionHandlerInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NativeSessionBlockingTest extends TestCase
{
    public function test_blocking_loads_latest_persisted_session_before_read_and_releases_after_write(): void
    {
        $sessionId = str_repeat('a', 40);
        $handler = new RecordingSessionHandler([
            $sessionId => json_encode(['_token' => 'synthetic-token'], JSON_THROW_ON_ERROR),
        ]);
        $first = new Store('business', $handler, $sessionId, 'json');
        $second = new Store('business', $handler, $sessionId, 'json');
        $firstManager = new RecordingSessionManager($this->app);
        $firstManager->current = $first;
        $secondManager = new RecordingSessionManager($this->app);
        $secondManager->current = $second;
        $locks = new RecordingLockRepository($handler);
        $firstMiddleware = new RecordingStartSession($firstManager, $locks);
        $secondMiddleware = new RecordingStartSession($secondManager, $locks);

        $route = Route::get('/synthetic-session', static fn (): Response => response('ok'));
        $firstRequest = Request::create('/synthetic-session', 'POST', [], ['business' => $sessionId]);
        $firstRequest->setRouteResolver(static fn () => $route);
        $secondRequest = Request::create('/synthetic-session', 'POST', [], ['business' => $sessionId]);
        $secondRequest->setRouteResolver(static fn () => $route);
        $secondFiber = new Fiber(function () use ($secondMiddleware, $secondRequest, $second): void {
            $secondMiddleware->handle($secondRequest, function (Request $request) use ($second): Response {
                $this->assertSame('key-1', $second->get('pending.first'));
                $second->put('pending.second', 'key-2');

                return response('second');
            });
        });

        $firstMiddleware->handle($firstRequest, function (Request $request) use ($first, $secondFiber, $handler): Response {
            $first->put('pending.first', 'key-1');
            $this->assertSame('waiting-for-session-lock', $secondFiber->start());
            $this->assertSame(['lock.acquire.1', 'session.read', 'lock.wait.2'], $handler->events);

            return response('first');
        });
        $this->assertTrue($secondFiber->isSuspended());
        $secondFiber->resume();
        $this->assertTrue($secondFiber->isTerminated());

        $this->assertSame([
            'lock.acquire.1',
            'session.read',
            'lock.wait.2',
            'session.write.'.$sessionId,
            'lock.release.1',
            'lock.acquire.2',
            'session.read',
            'session.write.'.$sessionId,
            'lock.release.2',
        ], $handler->events);
        $this->assertSame('key-1', data_get($handler->decoded($sessionId), 'pending.first'));
        $this->assertSame('key-2', data_get($handler->decoded($sessionId), 'pending.second'));
    }
}

final class RecordingSessionHandler implements SessionHandlerInterface
{
    /** @var array<string, string> */
    private array $sessions;

    /** @var list<string> */
    public array $events = [];

    /** @param array<string, string> $sessions */
    public function __construct(array $sessions)
    {
        $this->sessions = $sessions;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $this->events[] = 'session.read';

        return $this->sessions[$id] ?? '';
    }

    public function write(string $id, string $data): bool
    {
        $this->events[] = 'session.write.'.$id;
        $this->sessions[$id] = $data;

        return true;
    }

    public function destroy(string $id): bool
    {
        unset($this->sessions[$id]);

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }

    /** @return array<string, mixed> */
    public function decoded(string $id): array
    {
        $decoded = json_decode($this->sessions[$id] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}

final class RecordingSessionManager extends SessionManager
{
    public ?Store $current = null;

    public function shouldBlock(): bool
    {
        return true;
    }

    public function blockDriver(): string
    {
        return 'synthetic';
    }

    public function driver($driver = null): Store
    {
        if (! $this->current instanceof Store) {
            throw new \LogicException('The synthetic session was not selected.');
        }

        return $this->current;
    }

    /** @return array<string, mixed> */
    public function getSessionConfig(): array
    {
        return [
            'driver' => 'synthetic',
            'lifetime' => 120,
            'expire_on_close' => false,
            'lottery' => [0, 100],
            'cookie' => 'business',
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'http_only' => true,
            'same_site' => 'lax',
            'partitioned' => false,
        ];
    }

    public function defaultRouteBlockLockSeconds(): int
    {
        return 60;
    }

    public function defaultRouteBlockWaitSeconds(): int
    {
        return 30;
    }
}

final class RecordingStartSession extends StartSession
{
    public function __construct(
        RecordingSessionManager $recordingManager,
        private readonly RecordingLockRepository $locks,
    ) {
        parent::__construct($recordingManager, static fn (): object => new \stdClass);
    }

    protected function cache($driver): RecordingLockRepository
    {
        return $this->locks;
    }
}

final class RecordingLockRepository
{
    /** @var array<string, int> */
    private array $owners = [];

    public function __construct(private readonly RecordingSessionHandler $handler) {}

    private int $sequence = 0;

    public function lock(string $name, int $seconds): RecordingLock
    {
        return new RecordingLock($this, ++$this->sequence, $name);
    }

    public function acquire(string $name, int $sequence): void
    {
        if (isset($this->owners[$name])) {
            $this->handler->events[] = 'lock.wait.'.$sequence;
            Fiber::suspend('waiting-for-session-lock');
        }
        if (isset($this->owners[$name])) {
            throw new \LogicException('The waiting request resumed before the lock was released.');
        }

        $this->owners[$name] = $sequence;
        $this->handler->events[] = 'lock.acquire.'.$sequence;
    }

    public function release(string $name, int $sequence): void
    {
        if (($this->owners[$name] ?? null) !== $sequence) {
            throw new \LogicException('A request tried to release another request\'s lock.');
        }

        unset($this->owners[$name]);
        $this->handler->events[] = 'lock.release.'.$sequence;
    }
}

final class RecordingLock implements Lock
{
    public function __construct(
        private readonly RecordingLockRepository $repository,
        private readonly int $sequence,
        private readonly string $name,
    ) {}

    public function get($callback = null): mixed
    {
        return $this->block(0, $callback);
    }

    public function block($seconds, $callback = null): mixed
    {
        $this->repository->acquire($this->name, $this->sequence);

        return true;
    }

    public function release(): bool
    {
        $this->repository->release($this->name, $this->sequence);

        return true;
    }

    public function owner(): string
    {
        return 'synthetic-owner-'.$this->sequence;
    }

    public function forceRelease(): void
    {
        $this->release();
    }

    public function betweenBlockedAttemptsSleepFor($milliseconds): static
    {
        return $this;
    }
}
