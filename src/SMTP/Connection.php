<?php

namespace Utopia\SMTP;

/**
 * Active SMTP TCP session.
 */
interface Connection
{
    public function getIp(): string;

    public function getPort(): int;

    public function readLine(): ?string;

    public function write(string $data): void;

    public function close(): void;
}
