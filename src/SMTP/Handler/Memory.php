<?php

namespace Utopia\SMTP\Handler;

use Utopia\SMTP\Handler as HandlerContract;
use Utopia\SMTP\Message;

class Memory implements HandlerContract
{
    /** @var list<array{message: Message, mailFrom: string, rcptTo: list<string>}> */
    protected array $messages = [];

    /**
     * @param list<string> $allowedRecipients When set, only these addresses are accepted.
     * @param list<string> $allowedSenders When set, only these MAIL FROM addresses are accepted.
     * @param bool $rejectAll When true, all messages are rejected.
     */
    public function __construct(
        protected array $allowedRecipients = [],
        protected array $allowedSenders = [],
        protected bool $rejectAll = false,
    ) {
    }

    public function handle(Message $message, string $mailFrom, array $rcptTo): Result
    {
        if ($this->rejectAll) {
            return Result::Rejected;
        }

        if ($this->allowedSenders !== [] && !in_array(strtolower($mailFrom), array_map('strtolower', $this->allowedSenders), true)) {
            return Result::Rejected;
        }

        if ($this->allowedRecipients !== []) {
            foreach ($rcptTo as $recipient) {
                if (!in_array(strtolower($recipient), array_map('strtolower', $this->allowedRecipients), true)) {
                    return Result::Rejected;
                }
            }
        }

        $this->messages[] = [
            'message' => $message,
            'mailFrom' => $mailFrom,
            'rcptTo' => $rcptTo,
        ];

        return Result::Accepted;
    }

    public function getName(): string
    {
        return 'memory';
    }

    /**
     * @return list<array{message: Message, mailFrom: string, rcptTo: list<string>}>
     */
    public function all(): array
    {
        return $this->messages;
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function clear(): void
    {
        $this->messages = [];
    }
}
