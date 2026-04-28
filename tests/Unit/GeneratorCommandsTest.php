<?php

declare(strict_types=1);

namespace Tests\Unit;

use Esoul\LaravelCqrs\Console\Commands\CommandHandlerMakeCommand;
use Esoul\LaravelCqrs\Console\Commands\CommandMakeCommand;
use Esoul\LaravelCqrs\Console\Commands\QueryHandlerMakeCommand;
use Esoul\LaravelCqrs\Console\Commands\QueryMakeCommand;
use Illuminate\Console\Application as ArtisanApplication;
use Illuminate\Foundation\Application;
use Tests\TestCase;

class GeneratorCommandsTest extends TestCase
{
    public function test_make_cqrs_command_uses_configured_namespaces_and_paths(): void
    {
        $app = $this->createApplication();
        $app['config']->set('cqrs', array_replace_recursive($app['config']->get('cqrs'), $this->generatorConfig($app->basePath(), $app->storagePath('framework/cache/discovery'))));
        $this->registerPsr4Autoloader([
            'Tests\\Generated\\Command\\' => $app['config']->get('cqrs.commands.path'),
            'Tests\\Generated\\CommandHandler\\' => $app['config']->get('cqrs.command_handlers.path'),
        ]);

        $artisan = $this->makeGeneratorArtisan($app);
        $exitCode = $artisan->call('make:cqrs-command', [
            'name' => 'CreateOrder',
            '--handler' => true,
            '--return' => 'string',
            '--no-interaction' => true,
        ]);

        $commandPath = $app['config']->get('cqrs.commands.path') . '/CreateOrderCommand.php';
        $handlerPath = $app['config']->get('cqrs.command_handlers.path') . '/CreateOrderCommandHandler.php';

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($commandPath);
        $this->assertFileExists($handlerPath);
        $this->assertStringContainsString('namespace Tests\\Generated\\Command;', file_get_contents($commandPath) ?: '');
        $this->assertStringContainsString('@implements CommandInterface<string>', file_get_contents($commandPath) ?: '');
        $this->assertStringContainsString('final readonly class CreateOrderCommand implements CommandInterface', file_get_contents($commandPath) ?: '');
        $this->assertStringContainsString('namespace Tests\\Generated\\CommandHandler;', file_get_contents($handlerPath) ?: '');
        $this->assertStringContainsString('use Tests\\Generated\\Command\\CreateOrderCommand;', file_get_contents($handlerPath) ?: '');
        $this->assertStringContainsString('public function handle(CommandInterface $command) : string', file_get_contents($handlerPath) ?: '');
    }

    public function test_make_cqrs_query_uses_configured_namespaces_and_paths(): void
    {
        $app = $this->createApplication();
        $app['config']->set('cqrs', array_replace_recursive($app['config']->get('cqrs'), $this->generatorConfig($app->basePath(), $app->storagePath('framework/cache/discovery'))));
        $this->registerPsr4Autoloader([
            'Tests\\Generated\\Query\\' => $app['config']->get('cqrs.queries.path'),
            'Tests\\Generated\\QueryHandler\\' => $app['config']->get('cqrs.query_handlers.path'),
        ]);

        $artisan = $this->makeGeneratorArtisan($app);
        $exitCode = $artisan->call('make:cqrs-query', [
            'name' => 'FindOrder',
            '--handler' => true,
            '--return' => 'array',
            '--no-interaction' => true,
        ]);

        $queryPath = $app['config']->get('cqrs.queries.path') . '/FindOrderQuery.php';
        $handlerPath = $app['config']->get('cqrs.query_handlers.path') . '/FindOrderQueryHandler.php';

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($queryPath);
        $this->assertFileExists($handlerPath);
        $this->assertStringContainsString('namespace Tests\\Generated\\Query;', file_get_contents($queryPath) ?: '');
        $this->assertStringContainsString('@implements QueryInterface<array>', file_get_contents($queryPath) ?: '');
        $this->assertStringContainsString('namespace Tests\\Generated\\QueryHandler;', file_get_contents($handlerPath) ?: '');
        $this->assertStringContainsString('use Tests\\Generated\\Query\\FindOrderQuery;', file_get_contents($handlerPath) ?: '');
        $this->assertStringContainsString('public function handle(QueryInterface $query): array', file_get_contents($handlerPath) ?: '');
    }

    public function test_make_cqrs_command_handler_accepts_command_option_value(): void
    {
        $app = $this->createApplication();
        $app['config']->set('cqrs', array_replace_recursive($app['config']->get('cqrs'), $this->generatorConfig($app->basePath(), $app->storagePath('framework/cache/discovery'))));
        $this->registerPsr4Autoloader([
            'Tests\\Generated\\Command\\' => $app['config']->get('cqrs.commands.path'),
            'Tests\\Generated\\CommandHandler\\' => $app['config']->get('cqrs.command_handlers.path'),
        ]);

        $artisan = $this->makeGeneratorArtisan($app);
        $artisan->call('make:cqrs-command', [
            'name' => 'ShipOrder',
            '--return' => 'string',
            '--no-interaction' => true,
        ]);

        $exitCode = $artisan->call('make:cqrs-command-handler', [
            'name' => 'ShipOrderHandler',
            '--command' => 'ShipOrderCommand',
            '--return' => 'string',
            '--no-interaction' => true,
        ]);

        $handlerPath = $app['config']->get('cqrs.command_handlers.path') . '/ShipOrderHandler.php';

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($handlerPath);
        $this->assertStringContainsString('use Tests\\Generated\\Command\\ShipOrderCommand;', file_get_contents($handlerPath) ?: '');
    }

    public function test_make_cqrs_query_handler_accepts_query_option_value(): void
    {
        $app = $this->createApplication();
        $app['config']->set('cqrs', array_replace_recursive($app['config']->get('cqrs'), $this->generatorConfig($app->basePath(), $app->storagePath('framework/cache/discovery'))));
        $this->registerPsr4Autoloader([
            'Tests\\Generated\\Query\\' => $app['config']->get('cqrs.queries.path'),
            'Tests\\Generated\\QueryHandler\\' => $app['config']->get('cqrs.query_handlers.path'),
        ]);

        $artisan = $this->makeGeneratorArtisan($app);
        $artisan->call('make:cqrs-query', [
            'name' => 'LookupOrder',
            '--return' => 'array',
            '--no-interaction' => true,
        ]);

        $exitCode = $artisan->call('make:cqrs-query-handler', [
            'name' => 'LookupOrderHandler',
            '--query' => 'LookupOrderQuery',
            '--return' => 'array',
            '--no-interaction' => true,
        ]);

        $handlerPath = $app['config']->get('cqrs.query_handlers.path') . '/LookupOrderHandler.php';

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($handlerPath);
        $this->assertStringContainsString('use Tests\\Generated\\Query\\LookupOrderQuery;', file_get_contents($handlerPath) ?: '');
    }

    public function test_make_cqrs_command_handler_fails_cleanly_for_invalid_command_class(): void
    {
        $app = $this->createApplication();
        $app['config']->set('cqrs', array_replace_recursive($app['config']->get('cqrs'), $this->generatorConfig($app->basePath(), $app->storagePath('framework/cache/discovery'))));
        $this->registerPsr4Autoloader([
            'Tests\\Generated\\Command\\' => $app['config']->get('cqrs.commands.path'),
            'Tests\\Generated\\CommandHandler\\' => $app['config']->get('cqrs.command_handlers.path'),
        ]);

        $artisan = $this->makeGeneratorArtisan($app);
        $exitCode = $artisan->call('make:cqrs-command-handler', [
            'name' => 'BrokenHandler',
            '--command' => 'MissingCommand',
            '--return' => 'string',
            '--no-interaction' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString("The specified command class 'Tests\\Generated\\Command\\MissingCommand' does not exist.", $artisan->output());
    }

    public function test_make_cqrs_query_handler_fails_cleanly_for_invalid_query_class(): void
    {
        $app = $this->createApplication();
        $app['config']->set('cqrs', array_replace_recursive($app['config']->get('cqrs'), $this->generatorConfig($app->basePath(), $app->storagePath('framework/cache/discovery'))));
        $this->registerPsr4Autoloader([
            'Tests\\Generated\\Query\\' => $app['config']->get('cqrs.queries.path'),
            'Tests\\Generated\\QueryHandler\\' => $app['config']->get('cqrs.query_handlers.path'),
        ]);

        $artisan = $this->makeGeneratorArtisan($app);
        $exitCode = $artisan->call('make:cqrs-query-handler', [
            'name' => 'BrokenHandler',
            '--query' => 'MissingQuery',
            '--return' => 'array',
            '--no-interaction' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString("The specified query class 'Tests\\Generated\\Query\\MissingQuery' does not exist.", $artisan->output());
    }

    /**
     * @return array<string, mixed>
     */
    private function generatorConfig(string $basePath, string $cacheDirectory): array
    {
        return [
            'discovery' => [
                'enabled' => false,
                'cache_dir' => $cacheDirectory,
                'paths' => [],
            ],
            'commands' => [
                'base_namespace' => 'Tests\\Generated\\Command',
                'path' => $basePath . '/app/Generated/Commands',
                'register' => [],
            ],
            'queries' => [
                'base_namespace' => 'Tests\\Generated\\Query',
                'path' => $basePath . '/app/Generated/Queries',
                'register' => [],
            ],
            'command_handlers' => [
                'base_namespace' => 'Tests\\Generated\\CommandHandler',
                'path' => $basePath . '/app/Generated/CommandHandlers',
            ],
            'query_handlers' => [
                'base_namespace' => 'Tests\\Generated\\QueryHandler',
                'path' => $basePath . '/app/Generated/QueryHandlers',
            ],
        ];
    }

    private function makeGeneratorArtisan(Application $app): ArtisanApplication
    {
        $artisan = $this->makeArtisan($app);
        $artisan->resolveCommands([
            CommandMakeCommand::class,
            CommandHandlerMakeCommand::class,
            QueryMakeCommand::class,
            QueryHandlerMakeCommand::class,
        ])->setContainerCommandLoader();

        return $artisan;
    }
}
