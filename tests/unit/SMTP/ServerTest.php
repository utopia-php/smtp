<?php

namespace Tests\Unit\Utopia\SMTP;

use PHPUnit\Framework\TestCase;
use Utopia\SMTP\Adapter;
use Utopia\SMTP\Connection;
use Utopia\SMTP\Handler\Memory;
use Utopia\SMTP\Message;
use Utopia\SMTP\Message\Address;
use Utopia\SMTP\Server;

final class ServerTest extends TestCase
{
    public function testDeliversMessageThroughHandler(): void
    {
        $handler = new Memory();
        $server = new Server(new TestAdapter(), $handler, 'mail.test');
        $server->start();

        $this->assertSame(1, $handler->count());
        $stored = $handler->all()[0];
        $this->assertSame('sender@test.com', $stored['mailFrom']);
        $this->assertSame(['inbox@test.com'], $stored['rcptTo']);
        $this->assertSame('Hello server', $stored['message']->body);
    }
}

final class TestAdapter extends Adapter
{
    /** @var callable(Connection $connection): void|null */
    private mixed $handler = null;

    public function onWorkerStart(callable $callback): void
    {
        $callback(0);
    }

    public function onConnection(callable $callback): void
    {
        $this->handler = $callback;
    }

    public function start(): void
    {
        if ($this->handler === null) {
            return;
        }

        $message = Message::create(
            new Address('sender@test.com'),
            new Address('inbox@test.com'),
            'Subject',
            'Hello server',
        );

        $lines = [
            'EHLO client.test',
            'MAIL FROM:<sender@test.com>',
            'RCPT TO:<inbox@test.com>',
            'DATA',
            ...explode("\r\n", $message->encode()),
            '.',
            'QUIT',
        ];

        \call_user_func($this->handler, new TestConnection($lines));
    }
}

final class TestConnection implements Connection
{
    private int $index = 0;

    /**
     * @param list<string> $lines
     */
    public function __construct(private readonly array $lines)
    {
    }

    public function getIp(): string
    {
        return '127.0.0.1';
    }

    public function getPort(): int
    {
        return 40000;
    }

    public function readLine(): ?string
    {
        if (!isset($this->lines[$this->index])) {
            return null;
        }

        return $this->lines[$this->index++];
    }

    public function write(string $data): void
    {
    }

    public function close(): void
    {
    }
}
