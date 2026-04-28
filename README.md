# Laravel CQRS

Laravel integration for [`esoul-cz/cqrs`](https://github.com/eSoul-cz/cqrs). The package wires the command bus and query bus into the Laravel container, supports handler auto-discovery, and adds artisan generators for commands, queries, and their handlers.

## Requirements

- PHP `>= 8.5`
- Laravel `10.x`, `11.x`, `12.x`, or `13.x`

## Installation

Install directly from Packagist:

```bash
composer require esoul-cz/laravel-cqrs
```

Or install directly from GitHub:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/eSoul-cz/laravel-cqrs"
    }
  ]
}
```

Publish the configuration:

```bash
php artisan laravel-cqrs:install
```

That command publishes `config/cqrs.php`.

## What the package registers

The service provider registers these singletons in the Laravel container:

- `Esoul\Cqrs\Contracts\CommandBusInterface`
- `Esoul\Cqrs\Contracts\QueryBusInterface`

Both buses use Laravel's container to instantiate handlers, so constructor injection works out of the box.

The package also ships facade classes:

- `Esoul\LaravelCqrs\Facades\CommandBus`
- `Esoul\LaravelCqrs\Facades\QueryBus`

## Default structure

The published config defaults to this application structure:

- commands: `app/Domain/CQRS/Command`
- queries: `app/Domain/CQRS/Query`
- command namespace: `App\Domain\CQRS\Command`
- query namespace: `App\Domain\CQRS\Query`

The generators and handler discovery read these values from `config/cqrs.php`, so you can move the classes by changing the configured namespaces and paths.

## Configuration

Published config lives in [`config/cqrs.php`](config/cqrs.php).

### Discovery

By default, handler discovery is enabled:

```php
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
```

Each configured discovery path must define:

- `base_namespace`
- `path`

On boot, the package scans these paths for handlers marked with:

- `#[HandlesCommand(...)]`
- `#[HandlesQuery(...)]`

Discovered handlers are registered into the Laravel container and mapped into the corresponding bus.

### Manual registration

If you do not want discovery for some handlers, or you need explicit registration, use:

```php
'commands' => [
    'register' => [
        App\Domain\CQRS\Command\CreateOrderCommand::class
            => App\Domain\CQRS\Command\CreateOrderCommandHandler::class,
    ],
],

'queries' => [
    'register' => [
        App\Domain\CQRS\Query\FindOrderQuery::class
            => App\Domain\CQRS\Query\FindOrderQueryHandler::class,
    ],
],
```

The package validates these mappings during boot:

- command classes must implement `CommandInterface`
- command handlers must implement `CommandHandlerInterface`
- query classes must implement `QueryInterface`
- query handlers must implement `QueryHandlerInterface`

Invalid mappings fail fast with an `InvalidArgumentException`.

## Usage

### Dispatch a command

```php
<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command;

use Esoul\Cqrs\Contracts\CommandInterface;

/**
 * @implements CommandInterface<string>
 */
final readonly class CreateOrderCommand implements CommandInterface
{
    public function __construct(
        public string $number,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command;

use Esoul\Cqrs\Attributes\HandlesCommand;
use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;

#[HandlesCommand(CreateOrderCommand::class)]
final class CreateOrderCommandHandler implements CommandHandlerInterface
{
    /**
     * @param CreateOrderCommand $command
     */
    public function handle(CommandInterface $command): string
    {
        return 'Created order ' . $command->number;
    }
}
```

```php
<?php

use App\Domain\CQRS\Command\CreateOrderCommand;
use Esoul\Cqrs\Contracts\CommandBusInterface;

$result = app(CommandBusInterface::class)->dispatch(
    new CreateOrderCommand('ORD-001')
);
```

Using the facade:

```php
<?php

use App\Domain\CQRS\Command\CreateOrderCommand;
use Esoul\LaravelCqrs\Facades\CommandBus;

$result = CommandBus::dispatch(
    new CreateOrderCommand('ORD-001')
);
```

### Execute a query

```php
<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query;

use Esoul\Cqrs\Contracts\QueryInterface;

/**
 * @implements QueryInterface<array{id: int, number: string}|null>
 */
final readonly class FindOrderQuery implements QueryInterface
{
    public function __construct(
        public string $number,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query;

use Esoul\Cqrs\Attributes\HandlesQuery;
use Esoul\Cqrs\Contracts\QueryHandlerInterface;
use Esoul\Cqrs\Contracts\QueryInterface;

#[HandlesQuery(FindOrderQuery::class)]
final class FindOrderQueryHandler implements QueryHandlerInterface
{
    /**
     * @param FindOrderQuery $query
     * @return array{id: int, number: string}|null
     */
    public function handle(QueryInterface $query): ?array
    {
        return ['id' => 1, 'number' => $query->number];
    }
}
```

```php
<?php

use App\Domain\CQRS\Query\FindOrderQuery;
use Esoul\Cqrs\Contracts\QueryBusInterface;

$result = app(QueryBusInterface::class)->execute(
    new FindOrderQuery('ORD-001')
);
```

Using the facade:

```php
<?php

use App\Domain\CQRS\Query\FindOrderQuery;
use Esoul\LaravelCqrs\Facades\QueryBus;

$result = QueryBus::execute(
    new FindOrderQuery('ORD-001')
);
```

## Artisan generators

The package provides four generators:

```bash
php artisan make:cqrs-command CreateOrder
php artisan make:cqrs-command CreateOrder --handler
php artisan make:cqrs-command-handler CreateOrderCommand

php artisan make:cqrs-query FindOrder
php artisan make:cqrs-query FindOrder --handler
php artisan make:cqrs-query-handler FindOrderQuery
```

### Generated file locations

- command classes use `cqrs.commands.path`
- command handler classes use `cqrs.command_handlers.path`
- query classes use `cqrs.queries.path`
- query handler classes use `cqrs.query_handlers.path`

Their namespaces come from the matching `base_namespace` settings.

### Naming conventions

The generators normalize names for you:

- commands get the `Command` suffix
- command handlers get the `Handler` suffix
- queries get the `Query` suffix
- query handlers get the `Handler` suffix

Examples:

- `make:cqrs-command CreateOrder` => `CreateOrderCommand`
- `make:cqrs-command-handler CreateOrder` => `CreateOrderCommandHandler`
- `make:cqrs-query FindOrder` => `FindOrderQuery`
- `make:cqrs-query-handler FindOrder` => `FindOrderQueryHandler`

### Return types

`make:cqrs-command` and `make:cqrs-query` support `--return` and pass that type through to the generated phpdoc. Their handler generators also support `--return` to set the generated `handle()` return type.

Example:

```bash
php artisan make:cqrs-command CreateOrder --handler --return=string
php artisan make:cqrs-query FindOrder --handler --return='array{id:int,number:string}|null'
```

## Notes

- Discovery and manual registration can be used together.
- Facades are class-based only; no alias is registered automatically.
- If no handler is registered for a dispatched command or executed query, the underlying bus throws a runtime exception.
- Handler discovery depends on the `esoul-cz/cqrs` attributes, so handlers must be annotated correctly.
