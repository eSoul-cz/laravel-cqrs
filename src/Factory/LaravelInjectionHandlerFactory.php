<?php

declare(strict_types=1);

namespace Esoul\LaravelCqrs\Factory;

use Esoul\Cqrs\Contracts\HandlerFactoryInterface;
use Illuminate\Contracts\Container\BindingResolutionException;

class LaravelInjectionHandlerFactory implements HandlerFactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @throws BindingResolutionException
     */
    public function instantiate(string $handlerClass): object
    {
        return app()->make($handlerClass);
    }
}
