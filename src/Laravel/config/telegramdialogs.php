<?php declare(strict_types=1);

return [
    'stores' => [
        'redis' => [
            'connection' => env('TELEGRAM_DIALOGS_REDIS_CONNECTION', 'default'),
        ],
    ],
];
