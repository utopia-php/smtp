<?php

namespace Utopia\SMTP\Transport;

use Utopia\SMTP\Client;
use Utopia\SMTP\Message;
use Utopia\SMTP\Transport;

class Socket implements Transport
{
    protected Client $client;

    public function __construct(
        protected string $server = '127.0.0.1',
        protected int $port = 25,
        protected int $timeout = 5,
    ) {
        $this->client = new Client($server, $port, $timeout);
    }

    public function send(Message $message, ?string $mailFrom = null, array $rcptTo = []): void
    {
        $this->client->send($message, $mailFrom, $rcptTo);
    }

    public function getName(): string
    {
        return "Socket ({$this->server}:{$this->port})";
    }
}
