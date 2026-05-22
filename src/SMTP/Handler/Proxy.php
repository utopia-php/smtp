<?php

namespace Utopia\SMTP\Handler;

use Utopia\SMTP\Client;
use Utopia\SMTP\Handler as HandlerContract;
use Utopia\SMTP\Message;

class Proxy implements HandlerContract
{
    protected Client $client;

    public function __construct(
        protected string $server = '127.0.0.1',
        protected int $port = 25,
        protected int $timeout = 5,
    ) {
        $this->client = new Client($server, $port, $timeout);
    }

    public function handle(Message $message, string $mailFrom, array $rcptTo): Result
    {
        try {
            $this->client->send($message, $mailFrom, $rcptTo);

            return Result::Accepted;
        } catch (\Throwable) {
            return Result::Rejected;
        }
    }

    public function getName(): string
    {
        return "Proxy ({$this->server}:{$this->port})";
    }
}
