<?php

namespace Utopia\SMTP\Protocol;

use Utopia\SMTP\Exception\Protocol\DecodingException;

final readonly class Response
{
    public function __construct(
        public int $code,
        public string $message,
        public bool $multiline = false,
    ) {
        if ($code < 100 || $code > 599) {
            throw new DecodingException('SMTP response code must be between 100 and 599');
        }
    }

    public static function decode(string $line): self
    {
        $line = rtrim($line, "\r\n");

        if (strlen($line) < 4 || !ctype_digit(substr($line, 0, 3))) {
            throw new DecodingException('Invalid SMTP response line');
        }

        $code = (int) substr($line, 0, 3);
        $separator = $line[3] ?? ' ';
        $message = trim(substr($line, 4));

        if ($separator !== ' ' && $separator !== '-') {
            throw new DecodingException('Invalid SMTP response separator');
        }

        return new self($code, $message, $separator === '-');
    }

    public function encode(): string
    {
        return sprintf("%d %s\r\n", $this->code, $this->message);
    }

    public function isPositiveCompletion(): bool
    {
        return $this->code >= 200 && $this->code < 300;
    }

    public function isPositiveIntermediate(): bool
    {
        return $this->code >= 300 && $this->code < 400;
    }
}
