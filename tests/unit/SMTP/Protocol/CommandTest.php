<?php

namespace Tests\Unit\Utopia\SMTP\Protocol;

use PHPUnit\Framework\TestCase;
use Utopia\SMTP\Protocol\Command;

final class CommandTest extends TestCase
{
    public function testParseVerbOnly(): void
    {
        $command = Command::parse("QUIT\r\n");

        $this->assertSame('QUIT', $command->verb);
        $this->assertSame('', $command->argument);
    }

    public function testParseWithArgument(): void
    {
        $command = Command::parse("MAIL FROM:<sender@example.com>\r\n");

        $this->assertSame('MAIL', $command->verb);
        $this->assertSame('sender@example.com', $command->pathArgument());
    }
}
