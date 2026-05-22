<?php

namespace Tests\Unit\Utopia\SMTP\Connection;

use PHPUnit\Framework\TestCase;
use Utopia\SMTP\Connection;

abstract class ConnectionTestCase extends TestCase
{
    /**
     * @param list<string> $chunks
     */
    abstract protected function createConnection(array $chunks): Connection;

    public function testReadLineReturnsBufferedLines(): void
    {
        $connection = $this->createConnection(["220 localhost\r\n", "250 OK\r\n"]);

        $this->assertSame('220 localhost', $connection->readLine());
        $this->assertSame('250 OK', $connection->readLine());
        $this->assertNull($connection->readLine());
    }

    public function testReadLineSplitsAcrossChunks(): void
    {
        $connection = $this->createConnection(['250-', "OK\r\n"]);

        $this->assertSame('250-OK', $connection->readLine());
        $this->assertNull($connection->readLine());
    }

    public function testGetIpAndPort(): void
    {
        $connection = $this->createConnection([]);

        $this->assertSame('127.0.0.1', $connection->getIp());
        $this->assertGreaterThan(0, $connection->getPort());
    }

    public function testWriteAndClose(): void
    {
        $connection = $this->createConnection([]);
        $connection->write("221 Bye\r\n");
        $connection->close();

        $this->addToAssertionCount(1);
    }
}
