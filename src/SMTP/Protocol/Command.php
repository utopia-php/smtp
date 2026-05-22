<?php

namespace Utopia\SMTP\Protocol;

final readonly class Command
{
    public const string HELO = 'HELO';
    public const string EHLO = 'EHLO';
    public const string MAIL = 'MAIL';
    public const string RCPT = 'RCPT';
    public const string DATA = 'DATA';
    public const string RSET = 'RSET';
    public const string QUIT = 'QUIT';
    public const string NOOP = 'NOOP';
    public const string VRFY = 'VRFY';
    public const string AUTH = 'AUTH';

    public function __construct(
        public string $verb,
        public string $argument = '',
    ) {
    }

    public static function parse(string $line): self
    {
        $line = rtrim($line, "\r\n");

        if ($line === '') {
            return new self('EMPTY');
        }

        $space = strpos($line, ' ');
        if ($space === false) {
            return new self(strtoupper($line));
        }

        return new self(
            strtoupper(substr($line, 0, $space)),
            trim(substr($line, $space + 1)),
        );
    }

    public function pathArgument(): string
    {
        if (preg_match('/<([^>]*)>/', $this->argument, $matches) === 1) {
            return trim($matches[1]);
        }

        return trim($this->argument);
    }
}
