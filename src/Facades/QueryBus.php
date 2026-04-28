<?php

declare(strict_types=1);

namespace Esoul\LaravelCqrs\Facades;

use Esoul\Cqrs\Contracts\QueryBusInterface;
use Esoul\Cqrs\Contracts\QueryHandlerInterface;
use Esoul\Cqrs\Contracts\QueryInterface;
use Illuminate\Support\Facades\Facade;
use RuntimeException;

final class QueryBus extends Facade
{
    /**
     * Execute a CQRS query and returns its result
     *
     * @template TResult of mixed Return value of the query
     *
     * @param  QueryInterface<TResult>  $query
     * @return TResult
     */
    public static function execute(QueryInterface $query): mixed
    {
        /** @var QueryBusInterface|null $instance */
        $instance = self::getFacadeRoot();
        if (!($instance instanceof QueryBusInterface)) {
            throw new RuntimeException('A facade root has not been set.');
        }

        return $instance->execute($query);
    }

    /**
     * @template TResult of mixed Return value of the query
     *
     * @param  class-string<QueryInterface<TResult>>  $queryClass
     * @param  class-string<QueryHandlerInterface>  $handlerClass
     */
    public static function registerHandler(string $queryClass, string $handlerClass): void
    {
        /** @var QueryBusInterface|null $instance */
        $instance = self::getFacadeRoot();
        if (!($instance instanceof QueryBusInterface)) {
            throw new RuntimeException('A facade root has not been set.');
        }
        $instance->registerHandler($queryClass, $handlerClass);
    }

    protected static function getFacadeAccessor(): string
    {
        return QueryBusInterface::class;
    }
}
