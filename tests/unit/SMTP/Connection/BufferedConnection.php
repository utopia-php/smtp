<?php

namespace Tests\Unit\Utopia\SMTP\Connection;

use Utopia\SMTP\Connection;

/**
 * In-memory connection used to verify shared line-buffering behavior.
 */
final class BufferedConnection implements Connection
{
    private int $offset = 0;

    public function __construct(
        private readonly string $payload,
        private readonly string $ip = '127.0.0.1',
        private readonly int $port = 2525,
    ) {
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function readLine(): ?string
    {
        if ($this->offset >= strlen($this->payload)) {
            return null;
        }

        $remaining = substr($this->payload, $this->offset);
        $position = strpos($remaining, "\n");

        if ($position === false) {
            $line = $remaining;
            $this->offset = strlen($this->payload);

            return rtrim($line, "\r\n");
        }

        $line = substr($remaining, 0, $position + 1);
        $this->offset += $position + 1;

        return rtrim($line, "\r\n");
    }

    public function write(string $data): void
    {
    }

    public function close(): void
    {
    }
}
