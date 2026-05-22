<?php

namespace Utopia\SMTP;

use Throwable;
use Utopia\Span\Span;
use Utopia\Telemetry\Adapter as Telemetry;
use Utopia\Telemetry\Adapter\None as NoTelemetry;
use Utopia\Telemetry\Counter;
use Utopia\Telemetry\Histogram;

/**
 * SMTP server orchestrating adapters and message handlers.
 *
 * RFCs:
 * - RFC 5321: https://datatracker.ietf.org/doc/html/rfc5321
 * - RFC 5322: https://datatracker.ietf.org/doc/html/rfc5322
 */
class Server
{
    protected Adapter $adapter;
    protected Handler $handler;

    /** @var array<int, callable> */
    protected array $errors = [];

    protected bool $debug = false;

    protected ?Histogram $duration = null;
    protected ?Counter $sessionsTotal = null;
    protected ?Counter $messagesTotal = null;

    public function __construct(
        Adapter $adapter,
        Handler $handler,
        protected string $hostname = 'localhost',
    ) {
        $this->adapter = $adapter;
        $this->handler = $handler;
        $this->setTelemetry(new NoTelemetry());
    }

    public function setTelemetry(Telemetry $telemetry): void
    {
        $this->duration = $telemetry->createHistogram(
            'smtp.session.duration',
            's',
            null,
            ['ExplicitBucketBoundaries' => [0.01, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]]
        );

        $this->sessionsTotal = $telemetry->createCounter('smtp.sessions.total');
        $this->messagesTotal = $telemetry->createCounter('smtp.messages.total');
    }

    public function error(callable $handler): self
    {
        $this->errors[] = $handler;
        return $this;
    }

    /**
     * @param callable(Server $server, int $workerId): void $handler
     * @phpstan-param callable(Server $server, int $workerId): void $handler
     */
    public function onWorkerStart(callable $handler): self
    {
        $this->adapter->onWorkerStart(function (int $workerId) use ($handler) {
            \call_user_func($handler, $this, $workerId);
        });

        return $this;
    }

    public function setDebug(bool $status): self
    {
        $this->debug = $status;
        return $this;
    }

    protected function handleError(Throwable $error): void
    {
        foreach ($this->errors as $handler) {
            call_user_func($handler, $error);
        }
    }

    protected function onConnection(Connection $connection): void
    {
        $span = Span::init('smtp.session');
        $span->set('client.ip', $connection->getIp());
        $span->set('client.port', $connection->getPort());

        $start = microtime(true);
        $this->sessionsTotal?->add(1);

        $protocol = new Protocol($this->hostname);
        $connection->write($protocol->greeting());

        $close = false;

        try {
            while (($line = $connection->readLine()) !== null) {
                if ($this->debug) {
                    printf("C: %s\n", $line);
                }

                if ($protocol->getState() === Protocol::STATE_DATA && $line === '.') {
                    $responses = $protocol->finalize($this->handler);
                    foreach ($responses as $response) {
                        $connection->write($response);
                        if ($this->debug) {
                            printf("S: %s", $response);
                        }
                    }

                    if ($responses !== []) {
                        $this->messagesTotal?->add(1, [
                            'handler' => $this->handler->getName(),
                            'accepted' => str_contains($responses[0], '250'),
                        ]);
                    }

                    continue;
                }

                $responses = $protocol->handle($line);

                /** @var list<string> $responses */
                foreach ($responses as $response) {
                    $connection->write($response);
                    if ($this->debug) {
                        printf("S: %s", $response);
                    }
                }

                if (strtoupper(rtrim($line)) === 'QUIT') {
                    $close = true;
                    break;
                }
            }
        } catch (Throwable $error) {
            $span->setError($error);
            $this->handleError($error);
            $connection->write("421 Service unavailable\r\n");
        } finally {
            $duration = microtime(true) - $start;
            $this->duration?->record($duration);
            $span->set('smtp.session.duration', $duration);
            $span->finish();
            $connection->close();
        }

        if ($close) {
            return;
        }
    }

    public function start(): void
    {
        try {
            $this->adapter->onConnection($this->onConnection(...));
            $this->adapter->start();
        } catch (Throwable $error) {
            $this->handleError($error);
        }
    }
}
