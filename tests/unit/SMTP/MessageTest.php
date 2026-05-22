<?php

namespace Tests\Unit\Utopia\SMTP;

use PHPUnit\Framework\TestCase;
use Utopia\SMTP\Exception\Message\DecodingException;
use Utopia\SMTP\Message;
use Utopia\SMTP\Message\Address;

final class MessageTest extends TestCase
{
    public function testCreateAndEncodeRoundTrip(): void
    {
        $message = Message::create(
            from: new Address('sender@example.com', 'Sender'),
            to: new Address('recipient@example.com'),
            subject: 'Hello SMTP',
            body: "Line one\nLine two",
        );

        $encoded = $message->encode();
        $decoded = Message::decode($encoded);

        $this->assertSame('sender@example.com', $decoded->from->address);
        $this->assertSame('Sender', $decoded->from->name);
        $this->assertSame('recipient@example.com', $decoded->to[0]->address);
        $this->assertSame('Hello SMTP', $decoded->subject);
        $this->assertSame("Line one\nLine two", $decoded->body);
    }

    public function testDecodeParsesCcHeaders(): void
    {
        $raw = implode("\r\n", [
            'From: sender@example.com',
            'To: one@example.com, two@example.com',
            'Cc: cc@example.com',
            'Subject: Subject',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            '',
            'Body',
        ]);

        $message = Message::decode($raw);

        $this->assertCount(2, $message->to);
        $this->assertCount(1, $message->cc);
        $this->assertSame('cc@example.com', $message->cc[0]->address);
    }

    public function testAllRecipientsIncludesCcAndBcc(): void
    {
        $message = new Message(
            from: new Address('sender@example.com'),
            to: [new Address('one@example.com')],
            subject: 'Test',
            body: 'Body',
            cc: [new Address('cc@example.com')],
            bcc: [new Address('bcc@example.com')],
        );

        $recipients = array_map(fn (Address $address) => $address->address, $message->allRecipients());

        $this->assertEqualsCanonicalizing(
            ['one@example.com', 'cc@example.com', 'bcc@example.com'],
            $recipients,
        );
    }

    public function testDecodeThrowsWhenFromMissing(): void
    {
        $this->expectException(DecodingException::class);
        $this->expectExceptionMessage('Missing From header');

        Message::decode("To: a@example.com\r\n\r\nBody");
    }

    public function testConstructorRequiresRecipients(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Message(
            from: new Address('sender@example.com'),
            to: [],
            subject: 'Empty',
            body: 'Body',
        );
    }
}
