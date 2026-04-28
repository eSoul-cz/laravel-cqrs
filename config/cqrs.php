<?php

declare(strict_types=1);

use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;
use Esoul\Cqrs\Contracts\QueryHandlerInterface;
use Esoul\Cqrs\Contracts\QueryInterface;

return [
    /*
    |--------------------------------------------------------------------------
    | CQRS Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for storing the configuration related to the Command Query
    | Responsibility Segregation (CQRS) pattern in the application. You can
    | define settings for command and query handlers, middleware, and other
    | related components here.
    |
    */

    /**
     * Discovery settings for auto-discovering command and query handlers. You can specify the base namespace and path for your handlers, and the system will
     * automatically register them with the command and query buses. This allows for a more modular and organized structure for your CQRS components.
     *
     * Multiple discovery configurations can be defined for different contexts or modules within your application. Each configuration should specify a unique base namespace and path to ensure proper organization and avoid conflicts.
     */
    'discovery' => [
        'enabled' => true,
        'cache_dir' => storage_path('framework/cache/discovery'),
        'paths' => [
            'base' => [
                'base_namespace' => 'App\\Domain\\CQRS',
                'path' => app_path('Domain/CQRS'),
            ],
        ],
    ],

    'commands' => [
        'base_namespace' => 'App\\Domain\\CQRS\\Command',
        'path' => app_path('Domain/CQRS/Command'),

        /**
         * Manually register commands and their handlers for the command bus.
         *
         * @var array<class-string<CommandInterface>, class-string<CommandHandlerInterface>> $register
         */
        'register' => [],
    ],

    'queries' => [
        'base_namespace' => 'App\\Domain\\CQRS\\Query',
        'path' => app_path('Domain/CQRS/Query'),

        /**
         * Manually register queries and their handlers for the query bus.
         *
         * @var array<class-string<QueryInterface>, class-string<QueryHandlerInterface>> $register
         */
        'register' => [],
    ],

    'command_handlers' => [
        'base_namespace' => 'App\\Domain\\CQRS\\Command',
        'path' => app_path('Domain/CQRS/Command'),
    ],

    'query_handlers' => [
        'base_namespace' => 'App\\Domain\\CQRS\\Query',
        'path' => app_path('Domain/CQRS/Query'),
    ],
];
