<?php

namespace Tests\Unit\Utopia\SMTP;

use PHPUnit\Framework\TestCase;
use Utopia\SMTP\Client;

final class ClientTest extends TestCase
{
    public function testConstructorRejectsInvalidServer(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Server must be an IP address.');

        new Client('not-an-ip');
    }
}
