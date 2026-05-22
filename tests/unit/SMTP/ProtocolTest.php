<?php

namespace Tests\Unit\Utopia\SMTP;

use PHPUnit\Framework\TestCase;
use Utopia\SMTP\Handler\Memory;
use Utopia\SMTP\Message;
use Utopia\SMTP\Message\Address;
use Utopia\SMTP\Protocol;

final class ProtocolTest extends TestCase
{
    public function testSmtpSessionAcceptsMessage(): void
    {
        $handler = new Memory();
        $protocol = new Protocol('mail.test');

        $responses = [];
        $responses[] = $protocol->greeting();
        $responses = array_merge($responses, $protocol->handle('EHLO client.test'));
        $responses = array_merge($responses, $protocol->handle('MAIL FROM:<sender@test.com>'));
        $responses = array_merge($responses, $protocol->handle('RCPT TO:<inbox@test.com>'));

        $message = Message::create(
            from: new Address('sender@test.com'),
            to: new Address('inbox@test.com'),
            subject: 'Protocol',
            body: 'Hello',
        );

        $dataResponses = $protocol->handle('DATA');
        $this->assertSame(["354 End data with <CR><LF>.<CR><LF>\r\n"], $dataResponses);

        foreach (explode("\r\n", $message->encode()) as $line) {
            $protocol->handle($line);
        }

        $responses = $protocol->finalize($handler);

        $this->assertSame(["250 Message accepted\r\n"], $responses);
        $this->assertSame(1, $handler->count());
    }

    public function testMailRequiresHelo(): void
    {
        $protocol = new Protocol('mail.test');

        $responses = $protocol->handle('MAIL FROM:<sender@test.com>');

        $this->assertSame(["503 Send HELO/EHLO first\r\n"], $responses);
    }

    public function testRcptRequiresMailFrom(): void
    {
        $protocol = new Protocol('mail.test');
        $protocol->handle('EHLO client.test');

        $responses = $protocol->handle('RCPT TO:<inbox@test.com>');

        $this->assertSame(["503 Need MAIL FROM first\r\n"], $responses);
    }

    public function testMemoryHandlerCanReject(): void
    {
        $handler = new Memory(rejectAll: true);
        $protocol = new Protocol('mail.test');
        $protocol->handle('EHLO client.test');
        $protocol->handle('MAIL FROM:<sender@test.com>');
        $protocol->handle('RCPT TO:<inbox@test.com>');
        $protocol->handle('DATA');
        $protocol->handle('From: sender@test.com');
        $protocol->handle('To: inbox@test.com');
        $protocol->handle('Subject: Test');
        $protocol->handle('');
        $protocol->handle('Body');

        $responses = $protocol->finalize($handler);

        $this->assertSame(["550 Message rejected\r\n"], $responses);
        $this->assertSame(0, $handler->count());
    }

    public function testQuitReturnsBye(): void
    {
        $protocol = new Protocol('mail.test');
        $protocol->handle('EHLO client.test');

        $responses = $protocol->handle('QUIT');

        $this->assertSame(["221 Bye\r\n"], $responses);
    }
}
