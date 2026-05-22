<?php

namespace Tests\E2E\Utopia\SMTP;

use PHPUnit\Framework\TestCase;
use Utopia\SMTP\Client;
use Utopia\SMTP\Message;
use Utopia\SMTP\Message\Address;

final class ClientTest extends TestCase
{
    public const int PORT = 2525;

    public function testSendMessageToMemoryServer(): void
    {
        $client = new Client('127.0.0.1', self::PORT);

        $message = Message::create(
            from: new Address('sender@appwrite.test', 'Sender'),
            to: new Address('inbox@appwrite.test'),
            subject: 'E2E SMTP',
            body: 'Delivered by client',
        );

        $client->send($message, 'sender@appwrite.test', ['inbox@appwrite.test']);

        $this->addToAssertionCount(1);
    }

    public function testRejectedRecipientThrows(): void
    {
        $client = new Client('127.0.0.1', self::PORT);

        $message = Message::create(
            from: new Address('sender@appwrite.test'),
            to: new Address('unknown@appwrite.test'),
            subject: 'Rejected',
            body: 'Should fail',
        );

        $this->expectException(\Exception::class);

        $client->send($message, 'sender@appwrite.test', ['unknown@appwrite.test']);
    }
}
