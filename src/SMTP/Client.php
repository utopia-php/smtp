<?php

namespace Utopia\SMTP;

use Exception;
use Utopia\SMTP\Protocol\Response;
use Utopia\Validator\IP;

class Client
{
    public function __construct(
        protected string $server = '127.0.0.1',
        protected int $port = 25,
        protected int $timeout = 5,
    ) {
        $validator = new IP(IP::ALL);
        if (!$validator->isValid($server)) {
            throw new Exception('Server must be an IP address.');
        }
    }

    /**
     * @param list<string> $rcptTo When empty, recipients are taken from the message.
     */
    public function send(Message $message, ?string $mailFrom = null, array $rcptTo = []): void
    {
        $mailFrom ??= $message->from->address;
        $rcptTo = $rcptTo !== [] ? $rcptTo : array_map(
            fn ($address) => $address->address,
            $message->allRecipients()
        );

        $socket = $this->connect();

        try {
            $this->expect($this->readResponse($socket), 220);
            $this->command($socket, 'EHLO localhost');
            $this->expect($this->readResponse($socket), 250);
            $this->command($socket, "MAIL FROM:<{$mailFrom}>");
            $this->expect($this->readResponse($socket), 250);

            foreach ($rcptTo as $recipient) {
                $this->command($socket, "RCPT TO:<{$recipient}>");
                $this->expect($this->readResponse($socket), 250);
            }

            $this->command($socket, 'DATA');
            $this->expect($this->readResponse($socket), 354);
            $this->sendData($socket, $message->encode());
            $this->expect($this->readResponse($socket), 250);
            $this->command($socket, 'QUIT');
            $this->readResponse($socket);
        } finally {
            fclose($socket);
        }
    }

    /**
     * @return resource
     */
    protected function connect()
    {
        $targetHost = $this->formatTcpHost($this->server);
        $uri = "tcp://{$targetHost}:{$this->port}";

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($uri, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);

        if ($socket === false) {
            $errCode = is_int($errno) ? $errno : 0;
            $errMsg = is_string($errstr) ? $errstr : 'Unknown error';
            throw new Exception("Failed to connect to {$this->server}:{$this->port}: $errMsg ($errCode)");
        }

        stream_set_timeout($socket, $this->timeout);

        return $socket;
    }

    /**
     * @param resource $socket
     */
    protected function command(mixed $socket, string $command): void
    {
        $written = fwrite($socket, $command . "\r\n");

        if ($written === false) {
            throw new Exception('Failed to send SMTP command.');
        }
    }

    /**
     * @param resource $socket
     */
    protected function sendData(mixed $socket, string $payload): void
    {
        $payload = str_replace(["\r\n", "\r"], "\n", $payload);
        $lines = explode("\n", $payload);

        foreach ($lines as $line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }

            $written = fwrite($socket, $line . "\r\n");

            if ($written === false) {
                throw new Exception('Failed to send message data.');
            }
        }

        $written = fwrite($socket, ".\r\n");

        if ($written === false) {
            throw new Exception('Failed to end message data.');
        }
    }

    /**
     * @param resource $socket
     */
    protected function readResponse(mixed $socket): Response
    {
        $line = $this->readLine($socket);

        if ($line === null) {
            throw new Exception('SMTP connection closed unexpectedly.');
        }

        $response = Response::decode($line);

        while ($response->multiline) {
            $next = $this->readLine($socket);

            if ($next === null) {
                throw new Exception('Incomplete multiline SMTP response.');
            }

            $continuation = Response::decode($next);

            if (!$continuation->multiline) {
                $response = $continuation;
                break;
            }
        }

        return $response;
    }

    /**
     * @param resource $socket
     */
    protected function readLine(mixed $socket): ?string
    {
        if (!is_resource($socket)) {
            return null;
        }

        $line = fgets($socket);

        if ($line === false) {
            return null;
        }

        return rtrim($line, "\r\n");
    }

    protected function expect(Response $response, int $code): void
    {
        if ($response->code !== $code) {
            throw new Exception("Unexpected SMTP response {$response->code}: {$response->message}");
        }
    }

    protected function formatTcpHost(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return '[' . $host . ']';
        }

        return $host;
    }
}
