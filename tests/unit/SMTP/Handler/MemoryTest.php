<?php

namespace Tests\Unit\Utopia\SMTP\Handler;

use PHPUnit\Framework\TestCase;
use Utopia\SMTP\Handler\Memory;
use Utopia\SMTP\Handler\Result;
use Utopia\SMTP\Message;
use Utopia\SMTP\Message\Address;

final class MemoryTest extends TestCase
{
    public function testAllowedRecipientsFilter(): void
    {
        $handler = new Memory(allowedRecipients: ['allowed@example.com']);
        $message = Message::create(
            new Address('sender@example.com'),
            new Address('allowed@example.com'),
            'Subject',
            'Body',
        );

        $accepted = $handler->handle($message, 'sender@example.com', ['allowed@example.com']);
        $rejected = $handler->handle($message, 'sender@example.com', ['blocked@example.com']);

        $this->assertSame(Result::Accepted, $accepted);
        $this->assertSame(Result::Rejected, $rejected);
        $this->assertSame(1, $handler->count());
    }
}
