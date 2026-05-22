<?php

namespace Tests\Unit\Utopia\SMTP\Adapter;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Utopia\SMTP\Adapter;
use Utopia\SMTP\Adapter\Swoole;

#[RequiresPhpExtension('swoole')]
final class SwooleTest extends AdapterTestCase
{
    protected function createAdapter(int $port): Adapter
    {
        return new Swoole('127.0.0.1', $port);
    }

    protected function expectedName(): string
    {
        return 'swoole';
    }
}
