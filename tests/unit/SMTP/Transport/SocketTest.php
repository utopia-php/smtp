<?php

namespace Tests\Unit\Utopia\SMTP\Transport;

use PHPUnit\Framework\TestCase;
use Utopia\SMTP\Transport\Socket;

final class SocketTest extends TestCase
{
    public function testGetName(): void
    {
        $transport = new Socket('10.0.0.5', 2525);

        $this->assertSame('Socket (10.0.0.5:2525)', $transport->getName());
    }
}
