<?php

declare(strict_types=1);

namespace Tests\Unit;

use Esoul\Cqrs\Contracts\CommandBusInterface;
use Esoul\Cqrs\Contracts\QueryBusInterface;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Illuminate\Support\Facades\Facade;
use RuntimeException;
use Tests\Stubs\CQRS\TestingCommand;
use Tests\Stubs\CQRS\TestingCommandHandler;
use Tests\Stubs\CQRS\TestingQuery;
use Tests\Stubs\CQRS\TestingQueryHandler;
use Tests\TestCase;

class FacadeTest extends TestCase
{
    public function test_command_bus_facade_dispatches_commands(): void
    {
        $app = $this->createApplication([
            'discovery' => [
                'enabled' => false,
                'cache_dir' => sys_get_temp_dir() . '/laravel-cqrs-facade-cache-' . bin2hex(random_bytes(8)),
                'paths' => [],
            ],
            'commands' => [
                'register' => [
                    TestingCommand::class => TestingCommandHandler::class,
                ],
            ],
        ]);
        $this->bootPackage($app);

        $this->assertSame('Handled!', CommandBus::dispatch(new TestingCommand()));
    }

    public function test_command_bus_facade_register_handler_delegates_to_bound_bus(): void
    {
        $app = $this->createApplication([
            'discovery' => [
                'enabled' => false,
                'cache_dir' => sys_get_temp_dir() . '/laravel-cqrs-facade-cache-' . bin2hex(random_bytes(8)),
                'paths' => [],
            ],
        ]);
        $this->bootPackage($app);

        CommandBus::registerHandler(TestingCommand::class, TestingCommandHandler::class);

        $this->assertSame('Handled!', $app->make(CommandBusInterface::class)->dispatch(new TestingCommand()));
    }

    public function test_query_bus_facade_executes_queries(): void
    {
        $app = $this->createApplication([
            'discovery' => [
                'enabled' => false,
                'cache_dir' => sys_get_temp_dir() . '/laravel-cqrs-facade-cache-' . bin2hex(random_bytes(8)),
                'paths' => [],
            ],
            'queries' => [
                'register' => [
                    TestingQuery::class => TestingQueryHandler::class,
                ],
            ],
        ]);
        $this->bootPackage($app);

        $this->assertSame('Handled!', QueryBus::execute(new TestingQuery()));
    }

    public function test_query_bus_facade_register_handler_delegates_to_bound_bus(): void
    {
        $app = $this->createApplication([
            'discovery' => [
                'enabled' => false,
                'cache_dir' => sys_get_temp_dir() . '/laravel-cqrs-facade-cache-' . bin2hex(random_bytes(8)),
                'paths' => [],
            ],
        ]);
        $this->bootPackage($app);

        QueryBus::registerHandler(TestingQuery::class, TestingQueryHandler::class);

        $this->assertSame('Handled!', $app->make(QueryBusInterface::class)->execute(new TestingQuery()));
    }

    public function test_facades_throw_when_no_root_is_set(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A facade root has not been set.');

        CommandBus::dispatch(new TestingCommand());
    }
}
