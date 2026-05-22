<?php

namespace Utopia\SMTP;

use Utopia\SMTP\Handler\Result;

/**
 * Handles accepted SMTP messages.
 */
interface Handler
{
    public function getName(): string;

    /**
     * @param list<string> $rcptTo
     */
    public function handle(Message $message, string $mailFrom, array $rcptTo): Result;
}
