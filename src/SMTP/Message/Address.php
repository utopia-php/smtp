<?php

namespace Utopia\SMTP\Message;

use Utopia\SMTP\Exception\Message\DecodingException;

final readonly class Address
{
    public function __construct(
        public string $address,
        public ?string $name = null,
    ) {
        if ($address === '') {
            throw new DecodingException('Email address cannot be empty');
        }

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new DecodingException("Invalid email address: {$address}");
        }
    }

    public static function parse(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new DecodingException('Address value cannot be empty');
        }

        if (preg_match('/^<([^>]+)>$/', $value, $matches) === 1) {
            return new self(trim($matches[1]));
        }

        if (preg_match('/^(.+?)\s+<([^>]+)>$/', $value, $matches) === 1) {
            $name = trim($matches[1], " \t\"'");

            return new self(trim($matches[2]), $name !== '' ? $name : null);
        }

        return new self($value);
    }

    public function encode(): string
    {
        if ($this->name === null) {
            return $this->address;
        }

        $name = addcslashes($this->name, '"\\');

        return "\"{$name}\" <{$this->address}>";
    }
}
