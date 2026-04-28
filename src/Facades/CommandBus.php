<?php

declare(strict_types=1);

namespace Esoul\LaravelCqrs\Facades;

use Esoul\Cqrs\Contracts\CommandBusInterface;
use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;
use Illuminate\Support\Facades\Facade;
use RuntimeException;

final class CommandBus extends Facade
{
    /**
     * Dispatches a CQRS command and returns its result
     *
     * @template TResult of mixed Return value of the command
     *
     * @param  CommandInterface<TResult>  $command
     * @return TResult
     */
    public static function dispatch(CommandInterface $command): mixed
    {
        /** @var CommandBusInterface|null $instance */
        $instance = self::getFacadeRoot();
        if (!($instance instanceof CommandBusInterface)) {
            throw new RuntimeException('A facade root has not been set.');
        }

        return $instance->dispatch($command);
    }

    /**
     * @template TResult of mixed Return value of the command
     *
     * @param  class-string<CommandInterface<TResult>>  $commandClass
     * @param  class-string<CommandHandlerInterface>  $handlerClass
     */
    public static function registerHandler(string $commandClass, string $handlerClass): void
    {
        /** @var CommandBusInterface|null $instance */
        $instance = self::getFacadeRoot();
        if (!($instance instanceof CommandBusInterface)) {
            throw new RuntimeException('A facade root has not been set.');
        }
        $instance->registerHandler($commandClass, $handlerClass);
    }

    protected static function getFacadeAccessor(): string
    {
        return CommandBusInterface::class;
    }
}
