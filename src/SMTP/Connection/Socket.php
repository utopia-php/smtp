<?php

namespace Utopia\SMTP\Connection;

use Socket as PhpSocket;
use Utopia\SMTP\Connection;

final class Socket implements Connection
{
    protected string $buffer = '';

    public function __construct(
        protected PhpSocket $socket,
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
            $chunk = @socket_read($this->socket, 8192, PHP_BINARY_READ);

            if ($chunk === false) {
                $error = socket_last_error($this->socket);

                if (in_array($error, [SOCKET_EAGAIN, SOCKET_EWOULDBLOCK], true)) {
                    socket_clear_error($this->socket);
                    usleep(1000);
                    continue;
                }

                if ($this->buffer === '') {
                    return null;
                }

                break;
            }

            if ($chunk === '') {
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
            $written = @socket_write($this->socket, substr($data, $sent));

            if ($written === false) {
                $error = socket_last_error($this->socket);

                if (in_array($error, [SOCKET_EAGAIN, SOCKET_EWOULDBLOCK], true)) {
                    socket_clear_error($this->socket);
                    usleep(1000);
                    continue;
                }

                break;
            }

            $sent += $written;
        }
    }

    public function close(): void
    {
        @socket_close($this->socket);
    }
}
