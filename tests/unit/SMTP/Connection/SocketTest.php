<?php

namespace Tests\Unit\Utopia\SMTP\Connection;

use Socket as PhpSocket;
use Utopia\SMTP\Connection;
use Utopia\SMTP\Connection\Socket;

final class SocketTest extends ConnectionTestCase
{
    /**
     * @param list<string> $chunks
     */
    protected function createConnection(array $chunks): Connection
    {
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$server instanceof PhpSocket) {
            $this->markTestSkipped('Could not create TCP socket.');
        }

        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($server, '127.0.0.1', 0);
        socket_listen($server, 1);

        $port = 0;
        socket_getsockname($server, $address, $port);
        $port = is_int($port) ? $port : 0;

        if ($port === 0) {
            socket_close($server);
            $this->markTestSkipped('Could not resolve ephemeral port.');
        }

        $client = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$client instanceof PhpSocket) {
            socket_close($server);
            $this->markTestSkipped('Could not create client socket.');
        }

        socket_connect($client, '127.0.0.1', $port);
        $peer = socket_accept($server);

        if (!$peer instanceof PhpSocket) {
            socket_close($server);
            socket_close($client);
            $this->markTestSkipped('Could not accept peer socket.');
        }

        foreach ($chunks as $chunk) {
            socket_write($peer, $chunk);
        }

        socket_close($peer);
        socket_close($server);

        return new Socket($client, '127.0.0.1', $port);
    }

    public function testReadLineSplitsAcrossChunks(): void
    {
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$server instanceof PhpSocket) {
            $this->markTestSkipped('Could not create TCP socket.');
        }

        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($server, '127.0.0.1', 0);
        socket_listen($server, 1);

        $port = 0;
        socket_getsockname($server, $address, $port);
        $port = is_int($port) ? $port : 0;

        $client = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$client instanceof PhpSocket || $port === 0) {
            socket_close($server);
            $this->markTestSkipped('Could not create client socket.');
        }

        socket_connect($client, '127.0.0.1', $port);
        $peer = socket_accept($server);

        if (!$peer instanceof PhpSocket) {
            socket_close($server);
            socket_close($client);
            $this->markTestSkipped('Could not accept peer socket.');
        }

        socket_write($peer, '250-');

        $connection = new Socket($client, '127.0.0.1', $port);

        socket_write($peer, "OK\r\n");
        socket_close($peer);
        socket_close($server);

        $this->assertSame('250-OK', $connection->readLine());
    }

    public function testReadLineMatchesBufferedImplementation(): void
    {
        $payload = "220 localhost\r\n250 OK\r\n";
        $buffered = new BufferedConnection($payload);
        $socket = $this->createConnection(["220 localhost\r\n", "250 OK\r\n"]);

        $this->assertSame($buffered->readLine(), $socket->readLine());
        $this->assertSame($buffered->readLine(), $socket->readLine());
        $this->assertNull($socket->readLine());
    }
}
