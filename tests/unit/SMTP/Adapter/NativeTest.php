<?php

namespace Tests\Unit\Utopia\SMTP\Adapter;

use Utopia\SMTP\Adapter;
use Utopia\SMTP\Adapter\Native;

final class NativeTest extends AdapterTestCase
{
    protected function createAdapter(int $port): Adapter
    {
        return new Native('127.0.0.1', $port);
    }

    protected function expectedName(): string
    {
        return 'native';
    }
}
