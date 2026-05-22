<?php

namespace Utopia\SMTP;

/**
 * Sends email messages over SMTP.
 */
interface Transport
{
    public function getName(): string;

    /**
     * @param list<string> $rcptTo
     */
    public function send(Message $message, ?string $mailFrom = null, array $rcptTo = []): void;
}
