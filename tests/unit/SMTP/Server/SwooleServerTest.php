<?php

namespace Tests\Unit\Utopia\SMTP\Server;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Utopia\SMTP\Client;
use Utopia\SMTP\Message;
use Utopia\SMTP\Message\Address;

#[RequiresPhpExtension('swoole')]
final class SwooleServerTest extends TestCase
{
    public function testDeliversMessageThroughSwooleAdapter(): void
    {
        $port = 2526;
        $script = __DIR__ . '/../../resources/server.php';
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            ['php', $script],
            $descriptor,
            $pipes,
            dirname($script),
            [
                'PORT' => (string) $port,
                'ADAPTER' => 'swoole',
            ],
        );

        if (!is_resource($process)) {
            $this->fail('Could not start Swoole SMTP server process.');
        }

        try {
            $this->waitForPort($port);

            $client = new Client('127.0.0.1', $port);
            $message = Message::create(
                new Address('sender@appwrite.test'),
                new Address('inbox@appwrite.test'),
                'Swoole SMTP',
                'Delivered through Swoole adapter',
            );

            $client->send($message, 'sender@appwrite.test', ['inbox@appwrite.test']);
            $this->addToAssertionCount(1);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }

    protected function waitForPort(int $port, int $attempts = 50): void
    {
        for ($i = 0; $i < $attempts; $i++) {
            $socket = @fsockopen('127.0.0.1', $port);
            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(100000);
        }

        $this->fail("SMTP server did not listen on port {$port}.");
    }
}
