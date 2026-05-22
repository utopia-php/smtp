<?php

namespace Tests\Unit\Utopia\SMTP\Connection;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;
use Swoole\Server;
use Utopia\SMTP\Connection\Swoole as SwooleConnection;

#[RequiresPhpExtension('swoole')]
final class SwooleTest extends TestCase
{
    public function testReadLineMatchesBufferedImplementation(): void
    {
        $payload = "220 localhost\r\n250 OK\r\n";
        $buffered = new BufferedConnection($payload);
        $lines = [];

        Coroutine\run(function () use ($payload, &$lines) {
            $server = new Server('127.0.0.1', 0, SWOOLE_BASE, SWOOLE_SOCK_TCP);
            $connection = null;
            $port = 0;

            $server->on('Start', function (Server $server) use (&$port) {
                $socketName = $server->getSocket()?->getsockname();
                if (is_array($socketName) && isset($socketName['port']) && is_int($socketName['port'])) {
                    $port = $socketName['port'];
                }
            });

            $server->on('Connect', function (Server $server, int $fd) use (&$connection, $port, $payload) {
                $connection = new SwooleConnection($server, $fd, '127.0.0.1', 2525);

                Coroutine::create(function () use ($port, $payload) {
                    usleep(50000);
                    $client = new Coroutine\Socket(AF_INET, SOCK_STREAM, 0);
                    if ($client->connect('127.0.0.1', $port)) {
                        $client->send($payload);
                        $client->close();
                    }
                });
            });

            Coroutine::create(function () use ($server, &$connection, &$lines) {
                $server->start();

                if (!$connection instanceof SwooleConnection) {
                    return;
                }

                $lines[] = $connection->readLine();
                $lines[] = $connection->readLine();
                $lines[] = $connection->readLine();
            });
        });

        $this->assertSame($buffered->readLine(), $lines[0] ?? null);
        $this->assertSame($buffered->readLine(), $lines[1] ?? null);
        $this->assertArrayHasKey(2, $lines);
        $this->assertNull($lines[2]);
    }
}
