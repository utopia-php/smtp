<?php

namespace Utopia\SMTP;

abstract class Adapter
{
    /**
     * Worker start
     *
     * @param callable(int $workerId): void $callback
     * @phpstan-param callable(int $workerId): void $callback
     */
    abstract public function onWorkerStart(callable $callback): void;

    /**
     * Connection handler
     *
     * @param callable(Connection $connection): void $callback
     * @phpstan-param callable(Connection $connection): void $callback
     */
    abstract public function onConnection(callable $callback): void;

    /**
     * Start the SMTP server
     */
    abstract public function start(): void;

    public function getName(): string
    {
        return 'adapter';
    }
}
