<?php declare(strict_types=1);

namespace KootLabs\TelegramBotDialogs;

use KootLabs\TelegramBotDialogs\Exceptions\DialogException;
use Telegram\Bot\Actions;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Chat;
use Telegram\Bot\Objects\Update;

abstract class Dialog
{
    protected Api $bot;
    protected Update $update;
    protected array $memory = [];

    /** Seconds to store state of the dialog after latest activity on it. */
    protected int $ttl = 300;

    /** @var list<string|array<array-key, string|bool>> */
    protected array $steps = [];

    protected int $next = 0;
    protected int $current = 0;

    /** @param positive-int $next Step index. */
    final public function setNext(int $next): void
    {
        $this->next = $next;
    }

    final public function getNext(): int
    {
        return $this->next;
    }

    final public function setUpdate(Update $update): void
    {
        $this->update = $update;
    }

    final public function setBot(Api $bot): void
    {
        $this->bot = $bot;
    }

    /** Start dialog from the begging. */
    final public function start(): void
    {
        $this->next = 0;
        $this->proceed();
    }

    /**
     * @throws \KootLabs\TelegramBotDialogs\Exceptions\DialogException
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     */
    final public function proceed(): void
    {
        $this->current = $this->next;

        if ($this->isEnd()) {
            return;
        }
        $this->bot->sendChatAction([
            'chat_id' => $this->getChat()->id,
            'action' => Actions::TYPING,
        ]);

        $step = $this->steps[$this->current];

        if (is_array($step)) {
            $this->proceedConfiguredStep($step);
        } elseif (is_string($step)) {
            $stepMethodName = $step;

            if (! method_exists($this, $stepMethodName)) {
                throw new DialogException(sprintf('Public method “%s::%s()” is not available.', $this::class, $stepMethodName));
            }

            $this->$stepMethodName();
        } else {
            throw new DialogException('Dialog step is not defined.');
        }

        // Step forward only if did not change inside the step handler
        if ($this->next === $this->current) {
            ++$this->next;
        }
    }

    /** Jump to the particular step of the dialog */
    final public function jump(string $stepName): void
    {
        foreach ($this->steps as $index => $value) {
            if ($value === $stepName || (is_array($value) && $value['name'] === $stepName)) {
                $this->setNext($index);
                break;
            }
        }
    }

    /**
     * @todo Maybe the better way is that to return true/false from step-methods.
     * @todo ...And if it returns false - it means end of dialog
     */
    final public function end(): void
    {
        $this->next = count($this->steps);
    }

    /** Remember information for next steps. */
    final public function remember(string $key, mixed $value): void
    {
        $this->memory[$key] = $value;
    }

    /** Check if dialog ended */
    final public function isEnd(): bool
    {
        return $this->next >= count($this->steps);
    }

    /** Returns Telegram Chat */
    final public function getChat(): Chat
    {
        return $this->update->getMessage()->chat;
    }

    final public function ttl(): int
    {
        return $this->ttl;
    }

    /**
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     * @throws \KootLabs\TelegramBotDialogs\Exceptions\DialogException
     */
    private function proceedConfiguredStep(array $stepConfig): void
    {
        if (!isset($stepConfig['name'])) {
            throw new DialogException('Dialog step name must be defined.');
        }

        if (isset($stepConfig['response'])) {
            $params = [
                'chat_id' => $this->getChat()->id,
                'text' => $stepConfig['response'],
            ];

            if (!empty($stepConfig['options'])) {
                $params = array_merge($params, $stepConfig['options']);
            }

            $this->bot->sendMessage($params);
        }

        if (!empty($stepConfig['jump'])) {
            $this->jump($stepConfig['jump']);
        }

        if (isset($stepConfig['end']) && $stepConfig['end'] === true) {
            $this->end();
        }
    }

    public function __serialize(): array
    {
        return [
            'next' => $this->next,
            'memory' => $this->memory,
        ];
    }
}
