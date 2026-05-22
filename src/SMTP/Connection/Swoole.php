<?php

namespace Utopia\SMTP\Connection;

use Swoole\Server;
use Utopia\SMTP\Connection;

final class Swoole implements Connection
{
    protected string $buffer = '';

    public function __construct(
        protected Server $server,
        protected int $fd,
        protected string $ip,
        protected int $port,
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
        while (!str_contains($this->buffer, "\n")) {
            /** @var string|false $chunk */
            $chunk = $this->server->recv($this->fd, 8192); // @phpstan-ignore method.notFound

            if (!is_string($chunk) || $chunk === '') {
                if ($this->buffer === '') {
                    return null;
                }

                break;
            }

            $this->buffer .= $chunk;
        }

        $position = strpos($this->buffer, "\n");
        if ($position === false) {
            return null;
        }

        $line = substr($this->buffer, 0, $position + 1);
        $this->buffer = substr($this->buffer, $position + 1);

        return rtrim($line, "\r\n");
    }

    public function write(string $data): void
    {
        $total = strlen($data);
        $sent = 0;

        while ($sent < $total) {
            $written = $this->server->send($this->fd, substr($data, $sent));

            if (!is_int($written) || $written === 0) {
                break;
            }

            $sent += $written;
        }
    }

    public function close(): void
    {
        $this->server->close($this->fd);
    }
}
