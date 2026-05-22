<?php

namespace Utopia\SMTP\Adapter;

use Exception;
use Swoole\Coroutine;
use Swoole\Runtime;
use Swoole\Server;
use Utopia\SMTP\Adapter;
use Utopia\SMTP\Connection;
use Utopia\SMTP\Connection\Swoole as SwooleConnection;

class Swoole extends Adapter
{
    protected Server $server;

    /** @var list<callable(int $workerId): void> */
    protected array $onWorkerStart = [];

    /** @var callable(Connection $connection): void|null */
    protected mixed $onConnection = null;

    public function __construct(
        protected string $host = '0.0.0.0',
        protected int $port = 2525,
        protected int $numWorkers = 1,
        protected int $maxCoroutines = 3000,
    ) {
        $this->server = new Server($this->host, $this->port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $this->server->set([
            'worker_num' => $this->numWorkers,
            'max_coroutine' => $this->maxCoroutines,
            'enable_coroutine' => true,
        ]);
    }

    /**
     * @param callable(int $workerId): void $callback
     * @phpstan-param callable(int $workerId): void $callback
     */
    public function onWorkerStart(callable $callback): void
    {
        $this->onWorkerStart[] = $callback;
    }

    /**
     * @param callable(Connection $connection): void $callback
     * @phpstan-param callable(Connection $connection): void $callback
     */
    public function onConnection(callable $callback): void
    {
        $this->onConnection = $callback;
    }

    public function start(): void
    {
        if ($this->onConnection === null) {
            throw new Exception('Connection handler not registered.');
        }

        $handler = $this->onConnection;

        $this->server->on('WorkerStart', function ($server, $workerId) {
            if (!is_int($workerId)) {
                return;
            }

            foreach ($this->onWorkerStart as $callback) {
                \call_user_func($callback, $workerId);
            }
        });

        $this->server->on('Connect', function (Server $server, int $fd) use ($handler) {
            Coroutine::create(function () use ($server, $fd, $handler) {
                $info = $server->getClientInfo($fd);
                if (!is_array($info)) {
                    $server->close($fd);

                    return;
                }

                $ip = is_string($info['remote_ip'] ?? null) ? $info['remote_ip'] : '';
                $port = is_int($info['remote_port'] ?? null) ? $info['remote_port'] : 0;

                $connection = new SwooleConnection($server, $fd, $ip, $port);
                \call_user_func($handler, $connection);
            });
        });

        Runtime::enableCoroutine();
        $this->server->start();
    }

    public function getName(): string
    {
        return 'swoole';
    }
}
