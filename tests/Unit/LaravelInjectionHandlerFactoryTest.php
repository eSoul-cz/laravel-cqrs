<?php

declare(strict_types=1);

namespace Tests\Unit;

use Esoul\LaravelCqrs\Factory\LaravelInjectionHandlerFactory;
use Illuminate\Contracts\Container\BindingResolutionException;
use Tests\Stubs\Factory\ConstructedWithDependency;
use Tests\Stubs\Factory\ConstructedWithUnresolvableDependency;
use Tests\Stubs\Factory\InjectedDependency;
use Tests\TestCase;

class LaravelInjectionHandlerFactoryTest extends TestCase
{
    public function test_instantiate_resolves_class_from_laravel_container(): void
    {
        $app = $this->createApplication();
        $dependency = new InjectedDependency('resolved');
        $app->instance(InjectedDependency::class, $dependency);

        $instance = (new LaravelInjectionHandlerFactory())->instantiate(ConstructedWithDependency::class);

        $this->assertInstanceOf(ConstructedWithDependency::class, $instance);
        $this->assertSame($dependency, $instance->dependency);
    }

    public function test_instantiate_throws_for_unresolvable_dependency(): void
    {
        $this->createApplication();

        $this->expectException(BindingResolutionException::class);

        (new LaravelInjectionHandlerFactory())->instantiate(ConstructedWithUnresolvableDependency::class);
    }
}
