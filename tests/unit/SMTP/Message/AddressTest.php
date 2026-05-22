<?php

namespace Tests\Unit\Utopia\SMTP\Message;

use PHPUnit\Framework\TestCase;
use Utopia\SMTP\Exception\Message\DecodingException;
use Utopia\SMTP\Message\Address;

final class AddressTest extends TestCase
{
    public function testParseAngleAddress(): void
    {
        $address = Address::parse('<user@example.com>');

        $this->assertSame('user@example.com', $address->address);
        $this->assertNull($address->name);
    }

    public function testParseNamedAddress(): void
    {
        $address = Address::parse('Example User <user@example.com>');

        $this->assertSame('user@example.com', $address->address);
        $this->assertSame('Example User', $address->name);
    }

    public function testEncodeNamedAddress(): void
    {
        $address = new Address('user@example.com', 'Example User');

        $this->assertSame('"Example User" <user@example.com>', $address->encode());
    }

    public function testParseThrowsOnEmptyValue(): void
    {
        $this->expectException(DecodingException::class);

        Address::parse('');
    }
}
