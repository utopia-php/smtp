<?php

namespace Utopia\SMTP\Adapter;

use Exception;
use Socket;
use Utopia\SMTP\Adapter;
use Utopia\SMTP\Connection;
use Utopia\SMTP\Connection\Socket as SocketConnection;

class Native extends Adapter
{
    protected Socket $server;

    /** @var list<callable(int $workerId): void> */
    protected array $onWorkerStart = [];

    /** @var callable(Connection $connection): void|null */
    protected mixed $onConnection = null;

    /** @var array<int, Socket> */
    protected array $clients = [];

    public function __construct(
        protected string $host = '0.0.0.0',
        protected int $port = 2525,
        protected int $maxClients = 100,
        protected int $idleTimeout = 60,
    ) {
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$server instanceof Socket) {
            throw new Exception('Could not start SMTP server.');
        }

        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        $this->server = $server;
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

        if (socket_bind($this->server, $this->host, $this->port) === false) {
            throw new Exception('Could not bind SMTP server.');
        }

        if (socket_listen($this->server, 128) === false) {
            throw new Exception('Could not listen on SMTP server.');
        }

        socket_set_nonblock($this->server);

        foreach ($this->onWorkerStart as $callback) {
            \call_user_func($callback, 0);
        }

        $handler = $this->onConnection;

        /** @phpstan-ignore-next-line */
        while (true) {
            $this->closeIdleClients();

            $readSockets = [$this->server];
            foreach ($this->clients as $client) {
                $readSockets[] = $client;
            }

            $write = [];
            $except = [];
            $changed = socket_select($readSockets, $write, $except, 1);

            if ($changed === false || $changed === 0) {
                continue;
            }

            foreach ($readSockets as $socket) {
                if ($socket === $this->server) {
                    $client = @socket_accept($this->server);

                    if ($client instanceof Socket) {
                        if (count($this->clients) >= $this->maxClients) {
                            @socket_close($client);
                            continue;
                        }

                        if (@socket_set_nonblock($client) === false) {
                            @socket_close($client);
                            continue;
                        }

                        socket_set_option($client, SOL_SOCKET, SO_KEEPALIVE, 1);
                        $id = spl_object_id($client);
                        $this->clients[$id] = $client;
                        $this->clientActivity[$id] = time();

                        $ip = '';
                        $port = 0;
                        socket_getpeername($client, $ip, $port);

                        if (!is_string($ip) || !is_int($port)) {
                            $this->closeClient($client);
                            continue;
                        }

                        $connection = new SocketConnection($client, $ip, $port);
                        \call_user_func($handler, $connection);
                        $this->closeClient($client);
                    }

                    continue;
                }

                $this->clientActivity[spl_object_id($socket)] = time();
            }
        }
    }

    public function getName(): string
    {
        return 'native';
    }

    /** @var array<int, int> */
    protected array $clientActivity = [];

    protected function closeIdleClients(): void
    {
        $now = time();

        foreach ($this->clients as $id => $client) {
            $lastActivity = $this->clientActivity[$id] ?? 0;

            if (($now - $lastActivity) > $this->idleTimeout) {
                $this->closeClient($client);
            }
        }
    }

    protected function closeClient(Socket $client): void
    {
        $id = spl_object_id($client);
        unset($this->clients[$id], $this->clientActivity[$id]);
        @socket_close($client);
    }
}
