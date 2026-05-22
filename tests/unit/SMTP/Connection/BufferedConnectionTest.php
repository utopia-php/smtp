<?php

namespace Tests\Unit\Utopia\SMTP\Connection;

final class BufferedConnectionTest extends ConnectionTestCase
{
    /**
     * @param list<string> $chunks
     */
    protected function createConnection(array $chunks): BufferedConnection
    {
        return new BufferedConnection(implode('', $chunks));
    }
}
