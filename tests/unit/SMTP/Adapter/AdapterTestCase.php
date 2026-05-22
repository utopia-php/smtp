<?php

namespace Tests\Unit\Utopia\SMTP\Adapter;

use PHPUnit\Framework\TestCase;
use Utopia\SMTP\Adapter;

abstract class AdapterTestCase extends TestCase
{
    abstract protected function createAdapter(int $port): Adapter;

    abstract protected function expectedName(): string;

    public function testGetName(): void
    {
        $adapter = $this->createAdapter(0);

        $this->assertSame($this->expectedName(), $adapter->getName());
    }

    public function testStartThrowsWhenConnectionHandlerMissing(): void
    {
        $adapter = $this->createAdapter(0);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Connection handler not registered.');

        $adapter->start();
    }

    public function testOnWorkerStartAcceptsCallback(): void
    {
        $adapter = $this->createAdapter(0);
        $called = false;

        $adapter->onWorkerStart(function (int $workerId) use (&$called) {
            $called = $workerId === 0 || $workerId > 0;
        });

        $this->addToAssertionCount(1);
    }

    public function testOnConnectionAcceptsCallback(): void
    {
        $adapter = $this->createAdapter(0);

        $adapter->onConnection(function () {
        });

        $this->addToAssertionCount(1);
    }
}
