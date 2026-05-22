# Utopia SMTP

[![Tests](https://github.com/utopia-php/smtp/actions/workflows/ci.yml/badge.svg)](https://github.com/utopia-php/smtp/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/utopia-php/smtp.svg)](https://packagist.org/packages/utopia-php/smtp)

Utopia SMTP is a modern PHP 8.3 toolkit for building SMTP servers and clients. It provides a fully-typed RFC 5321/5322 message encoder/decoder, pluggable handlers, transports, and telemetry hooks so you can receive and relay email with minimal effort.

Although part of the [Utopia Framework](https://github.com/utopia-php/framework) family, the library is framework-agnostic and can be used in any PHP project.

## Installation

```bash
composer require utopia-php/smtp
```

The library requires PHP 8.3+ with the `ext-sockets` extension. The Swoole adapter additionally needs the `ext-swoole` extension.

## Quick start

Create an SMTP server by wiring an adapter (TCP socket implementation) and a handler (how messages are accepted). The example below uses the native PHP socket adapter and the in-memory handler.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Utopia\SMTP\Adapter\Native;
use Utopia\SMTP\Handler\Memory;
use Utopia\SMTP\Server;

$adapter = new Native('0.0.0.0', 2525);

$handler = new Memory(
    allowedRecipients: ['inbox@example.test'],
    allowedSenders: ['relay@example.test'],
);

$server = new Server($adapter, $handler, 'mail.example.test');
$server->setDebug(true);

$server->start();
```

Implement the [`Utopia\SMTP\Handler`](src/SMTP/Handler.php) interface to accept messages from databases, queues, or other stores.

## Handlers

- `Memory`: stores accepted messages in memory for testing or simple workloads
- `Proxy`: relays accepted messages to another SMTP server using the bundled client

Handlers can be combined with any adapter. Implementing the `Handler` interface allows you to plug in custom logic while reusing protocol and telemetry tooling.

## Adapters

- `Native`: pure PHP TCP server based on `ext-sockets`
- `Swoole`: non-blocking TCP server built on the Swoole runtime

Adapters are responsible only for accepting TCP connections. They call back into the server with a `Connection` so your handler logic stays isolated.

## SMTP client

The bundled client can deliver messages to any SMTP server.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Utopia\SMTP\Client;
use Utopia\SMTP\Message;
use Utopia\SMTP\Message\Address;

$client = new Client('127.0.0.1', 2525);

$message = Message::create(
    from: new Address('sender@example.test'),
    to: new Address('inbox@example.test'),
    subject: 'Hello from Utopia SMTP',
    body: 'Plain text body',
);

$client->send($message);
```

## Transports

- `Socket`: sends messages through the bundled SMTP client (mirrors DNS resolver transports)

```php
use Utopia\SMTP\Message;
use Utopia\SMTP\Message\Address;
use Utopia\SMTP\Transport\Socket;

$transport = new Socket('127.0.0.1', 2525);
$transport->send(Message::create(
    new Address('sender@example.test'),
    new Address('inbox@example.test'),
    'Subject',
    'Body',
));
```

## Telemetry

`Server::setTelemetry()` accepts any adapter from [`utopia-php/telemetry`](https://github.com/utopia-php/telemetry). Counters (`smtp.sessions.total`, `smtp.messages.total`) and a histogram (`smtp.session.duration`) are emitted automatically.

## Development

- Install dependencies: `composer install`
- Static analysis: `composer analyze`
- Coding standards: `composer format:check` (use `composer format` to auto-fix)
- Tests: `composer test`
- Sample server for manual and E2E testing: `docker compose up`

## License

MIT
