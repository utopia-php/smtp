<?php

namespace Utopia\SMTP;

use Utopia\SMTP\Exception\Message\DecodingException;
use Utopia\SMTP\Message\Address;

/**
 * RFC 5322 email message.
 */
final class Message
{
    /**
     * @param array<string, string> $headers
     * @param list<Address> $to
     * @param list<Address> $cc
     * @param list<Address> $bcc
     */
    public function __construct(
        public readonly Address $from,
        public readonly array $to,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $headers = [],
        public readonly array $cc = [],
        public readonly array $bcc = [],
        public readonly string $contentType = 'text/plain',
    ) {
        if ($to === []) {
            throw new \InvalidArgumentException('Message must have at least one recipient');
        }
    }

    public static function create(
        Address $from,
        Address $to,
        string $subject,
        string $body,
        string $contentType = 'text/plain',
    ): self {
        return new self($from, [$to], $subject, $body, contentType: $contentType);
    }

    public static function decode(string $raw): self
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $lines = explode("\n", $raw);

        if ($raw === '') {
            throw new DecodingException('Message body is empty');
        }

        /** @var array<string, string> $headers */
        $headers = [];
        $bodyLines = [];
        $inBody = false;
        $lastHeaderName = null;
        $lastHeaderValue = null;

        foreach ($lines as $line) {
            if (!$inBody) {
                if ($line === '') {
                    $inBody = true;
                    continue;
                }

                if ($lastHeaderName !== null && (($line[0] ?? '') === ' ' || ($line[0] ?? '') === "\t")) {
                    $lastHeaderValue .= ' ' . ltrim($line);
                    $headers[$lastHeaderName] = $lastHeaderValue;
                    continue;
                }

                if (!str_contains($line, ':')) {
                    throw new DecodingException('Invalid header line');
                }

                [$name, $value] = explode(':', $line, 2);
                $lastHeaderName = strtolower(trim($name));
                $lastHeaderValue = trim($value);
                $headers[$lastHeaderName] = $lastHeaderValue;
                continue;
            }

            $bodyLines[] = $line;
        }

        $fromHeader = $headers['from'] ?? null;
        if ($fromHeader === null) {
            throw new DecodingException('Missing From header');
        }

        $toHeader = $headers['to'] ?? null;
        if ($toHeader === null) {
            throw new DecodingException('Missing To header');
        }

        $subject = $headers['subject'] ?? '';
        $contentType = $headers['content-type'] ?? 'text/plain';
        $body = implode("\n", $bodyLines);

        return new self(
            from: Address::parse($fromHeader),
            to: self::parseAddressList($toHeader),
            subject: $subject,
            body: $body,
            headers: self::filterCustomHeaders($headers),
            cc: isset($headers['cc']) ? self::parseAddressList($headers['cc']) : [],
            bcc: isset($headers['bcc']) ? self::parseAddressList($headers['bcc']) : [],
            contentType: self::parseContentType($contentType),
        );
    }

    public function encode(): string
    {
        $lines = [
            'From: ' . $this->from->encode(),
            'To: ' . self::encodeAddressList($this->to),
            'Subject: ' . $this->encodeHeaderValue($this->subject),
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'MIME-Version: 1.0',
            'Content-Type: ' . $this->contentType . '; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        if ($this->cc !== []) {
            $lines[] = 'Cc: ' . self::encodeAddressList($this->cc);
        }

        if ($this->bcc !== []) {
            $lines[] = 'Bcc: ' . self::encodeAddressList($this->bcc);
        }

        foreach ($this->headers as $name => $value) {
            $lines[] = $name . ': ' . $this->encodeHeaderValue($value);
        }

        $lines[] = '';
        $lines[] = $this->body;

        return implode("\r\n", $lines);
    }

    /**
     * @return list<Address>
     */
    public function allRecipients(): array
    {
        return array_merge($this->to, $this->cc, $this->bcc);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private static function filterCustomHeaders(array $headers): array
    {
        $reserved = ['from', 'to', 'cc', 'bcc', 'subject', 'date', 'mime-version', 'content-type', 'content-transfer-encoding'];

        $custom = [];
        foreach ($headers as $name => $value) {
            if (!in_array($name, $reserved, true)) {
                $custom[$name] = $value;
            }
        }

        return $custom;
    }

    /**
     * @return list<Address>
     */
    private static function parseAddressList(string $value): array
    {
        $addresses = [];
        $parts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value) ?: [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $addresses[] = Address::parse($part);
        }

        if ($addresses === []) {
            throw new DecodingException('Recipient list cannot be empty');
        }

        return $addresses;
    }

    /**
     * @param list<Address> $addresses
     */
    private static function encodeAddressList(array $addresses): string
    {
        return implode(', ', array_map(fn (Address $address) => $address->encode(), $addresses));
    }

    private static function parseContentType(string $value): string
    {
        $parts = explode(';', $value);

        return trim($parts[0]);
    }

    private function encodeHeaderValue(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value) === 1) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }

        return $value;
    }
}
