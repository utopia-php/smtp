<?php

namespace Utopia\SMTP;

use Utopia\SMTP\Handler\Result;
use Utopia\SMTP\Protocol\Command;

/**
 * SMTP command handler for a single session.
 */
final class Protocol
{
    public const int STATE_GREETING = 0;
    public const int STATE_READY = 1;
    public const int STATE_MAIL = 2;
    public const int STATE_RCPT = 3;
    public const int STATE_DATA = 4;

    protected int $state = self::STATE_GREETING;

    protected string $heloName = '';

    protected string $mailFrom = '';

    /** @var list<string> */
    protected array $rcptTo = [];

    /** @var list<string> */
    protected array $dataBuffer = [];

    public function __construct(
        protected string $hostname = 'localhost',
    ) {
    }

    public function greeting(): string
    {
        return "220 {$this->hostname} ESMTP Utopia SMTP\r\n";
    }

    /**
     * @return list<string>
     */
    public function handle(string $line): array
    {
        if ($this->state === self::STATE_DATA) {
            return $this->handleDataLine($line);
        }

        $command = Command::parse($line);

        return match ($command->verb) {
            Command::HELO, Command::EHLO => $this->handleHelo($command),
            Command::MAIL => $this->handleMail($command),
            Command::RCPT => $this->handleRcpt($command),
            Command::DATA => $this->handleDataStart(),
            Command::RSET => $this->handleRset(),
            Command::QUIT => $this->handleQuit(),
            Command::NOOP => ["250 OK\r\n"],
            Command::VRFY => ["502 VRFY disabled\r\n"],
            Command::AUTH => ["502 AUTH not supported\r\n"],
            'EMPTY' => [],
            default => ["500 Command not recognized\r\n"],
        };
    }

    /**
     * @return list<string>
     */
    public function finalize(Handler $handler): array
    {
        if ($this->state !== self::STATE_DATA || $this->dataBuffer === []) {
            return [];
        }

        return $this->deliver($handler);
    }

    public function getState(): int
    {
        return $this->state;
    }

    /**
     * @return list<string>
     */
    protected function handleHelo(Command $command): array
    {
        $this->resetTransaction();
        $this->heloName = $command->argument !== '' ? $command->argument : 'localhost';
        $this->state = self::STATE_READY;

        if ($command->verb === Command::EHLO) {
            return [
                "250-{$this->hostname} Hello {$this->heloName}\r\n",
                "250-SIZE 10485760\r\n",
                "250 8BITMIME\r\n",
            ];
        }

        return ["250 {$this->hostname} Hello {$this->heloName}\r\n"];
    }

    /**
     * @return list<string>
     */
    protected function handleMail(Command $command): array
    {
        if ($this->state < self::STATE_READY) {
            return ["503 Send HELO/EHLO first\r\n"];
        }

        if ($this->state >= self::STATE_MAIL && $this->mailFrom !== '') {
            return ["503 Nested MAIL not allowed\r\n"];
        }

        $address = $command->pathArgument();
        if ($address === '') {
            return ["501 Syntax: MAIL FROM:<address>\r\n"];
        }

        $this->mailFrom = $address;
        $this->state = self::STATE_MAIL;

        return ["250 OK\r\n"];
    }

    /**
     * @return list<string>
     */
    protected function handleRcpt(Command $command): array
    {
        if ($this->state < self::STATE_MAIL || $this->mailFrom === '') {
            return ["503 Need MAIL FROM first\r\n"];
        }

        $address = $command->pathArgument();
        if ($address === '') {
            return ["501 Syntax: RCPT TO:<address>\r\n"];
        }

        $this->rcptTo[] = $address;
        $this->state = self::STATE_RCPT;

        return ["250 OK\r\n"];
    }

    /**
     * @return list<string>
     */
    protected function handleDataStart(): array
    {
        if ($this->state < self::STATE_RCPT || $this->rcptTo === []) {
            return ["503 Need RCPT TO first\r\n"];
        }

        $this->state = self::STATE_DATA;
        $this->dataBuffer = [];

        return ["354 End data with <CR><LF>.<CR><LF>\r\n"];
    }

    /**
     * @return list<string>
     */
    protected function handleDataLine(string $line): array
    {
        if ($line === '.') {
            return [];
        }

        if (str_starts_with($line, '..')) {
            $line = substr($line, 1);
        }

        $this->dataBuffer[] = $line;

        return [];
    }

    /**
     * @return list<string>
     */
    protected function deliver(Handler $handler): array
    {
        $raw = implode("\r\n", $this->dataBuffer);

        try {
            $message = Message::decode($raw);
        } catch (\Throwable) {
            $this->resetTransaction();
            $this->state = self::STATE_READY;

            return ["554 Message rejected\r\n"];
        }

        $result = $handler->handle($message, $this->mailFrom, $this->rcptTo);
        $this->resetTransaction();
        $this->state = self::STATE_READY;

        if ($result === Result::Accepted) {
            return ["250 Message accepted\r\n"];
        }

        return ["550 Message rejected\r\n"];
    }

    /**
     * @return list<string>
     */
    protected function handleRset(): array
    {
        $this->resetTransaction();
        $this->state = $this->heloName !== '' ? self::STATE_READY : self::STATE_GREETING;

        return ["250 OK\r\n"];
    }

    /**
     * @return list<string>
     */
    protected function handleQuit(): array
    {
        $this->heloName = 'QUIT';

        return ["221 Bye\r\n"];
    }

    protected function resetTransaction(): void
    {
        $this->mailFrom = '';
        $this->rcptTo = [];
        $this->dataBuffer = [];
    }
}
