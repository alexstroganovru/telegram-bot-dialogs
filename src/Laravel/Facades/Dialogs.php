<?php declare(strict_types=1);

namespace AlexStroganovRu\TelegramBotDialogs\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/** @mixin \AlexStroganovRu\TelegramBotDialogs\DialogManager */
final class Dialogs extends Facade
{
    /** Get the registered name of the component. */
    protected static function getFacadeAccessor(): string
    {
        return 'telegram.dialogs';
    }
}
