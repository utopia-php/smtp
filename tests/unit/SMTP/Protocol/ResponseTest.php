<?php

namespace Tests\Unit\Utopia\SMTP\Protocol;

use PHPUnit\Framework\TestCase;
use Utopia\SMTP\Exception\Protocol\DecodingException;
use Utopia\SMTP\Protocol\Response;

final class ResponseTest extends TestCase
{
    public function testDecodeFinalLine(): void
    {
        $response = Response::decode("250 OK\r\n");

        $this->assertSame(250, $response->code);
        $this->assertSame('OK', $response->message);
        $this->assertFalse($response->multiline);
        $this->assertTrue($response->isPositiveCompletion());
    }

    public function testDecodeMultilinePrefix(): void
    {
        $response = Response::decode("250-Hello localhost\r\n");

        $this->assertTrue($response->multiline);
    }

    public function testEncode(): void
    {
        $response = new Response(354, 'Start mail input');

        $this->assertSame("354 Start mail input\r\n", $response->encode());
    }

    public function testDecodeThrowsOnInvalidLine(): void
    {
        $this->expectException(DecodingException::class);

        Response::decode('invalid');
    }
}
