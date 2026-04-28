<?php

declare(strict_types=1);

namespace Esoul\LaravelCqrs\Console\Commands;

use Esoul\Cqrs\Contracts\QueryInterface;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('make:cqrs-query-handler')]
class QueryHandlerMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console query.
     *
     * @var string
     */
    protected $signature = 'make:cqrs-query-handler {name} {--query= : Query for which to create the handler} {--force : Overwrite existing files} {--return= : Return type of the handler method}';

    /**
     * The console query description.
     *
     * @var string
     */
    protected $description = 'Create a CQRS query handler class';

    protected $type = 'QueryHandler';

    public function handle(): bool
    {
        try {
            return parent::handle() !== false;
            /** @phpstan-ignore catch.neverThrown */
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return false;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->laravel->call([$this, 'handle']);

        if (is_int($result)) {
            return $result;
        }

        return $result ? self::SUCCESS : self::FAILURE;
    }

    protected function getStub(): string
    {
        return dirname(__DIR__) . '/stubs/queryHandler.stub';
    }

    protected function rootNamespace(): string
    {
        $namespace = config('cqrs.query_handlers.base_namespace');

        return is_string($namespace) && $namespace !== ''
            ? trim($namespace, '\\')
            : 'App\\Domain\\CQRS\\Query';
    }

    protected function getPath($name): string
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        $path = config('cqrs.query_handlers.path');
        $basePath = is_string($path) && $path !== ''
            ? rtrim($path, DIRECTORY_SEPARATOR)
            : app_path('Domain/CQRS/Query');

        return $basePath . DIRECTORY_SEPARATOR . ltrim(str_replace('\\', '/', $name), DIRECTORY_SEPARATOR) . '.php';
    }

    protected function getNameInput(): string
    {
        $name = parent::getNameInput();
        if (str_ends_with($name, 'Handler')) {
            return $name;
        }
        if (str_ends_with($name, 'Query')) {
            return $name . 'Handler';
        }

        return $name . 'QueryHandler';
    }

    protected function buildClass($name): string
    {
        $replace = $this->buildFactoryReplacements();

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }

    /**
     * @return array<string, string>
     */
    protected function buildFactoryReplacements(): array
    {
        $replacements = [];

        $queryName = $this->getFullQueryName();

        // Strip namespace from query
        $classname = $queryName;
        if (preg_match('/\\\\([\w]+)$/', $classname, $matches)) {
            $classname = $matches[1];
        }

        $replacements['{{ queryUse }}'] = 'use ' . $queryName . ';';
        $replacements['{{ query }}'] = ltrim($classname, '\\');

        $return = $this->option('return');
        $replacements['{{ return }}'] = $return && is_string($return) ? $return : 'mixed';

        return $replacements;
    }

    /**
     * @return class-string<QueryInterface<mixed>>
     */
    private function getFullQueryName(): string
    {
        $queryNamespace = config('cqrs.queries.base_namespace');
        $queryNamespace = is_string($queryNamespace) && $queryNamespace !== ''
            ? trim($queryNamespace, '\\')
            : 'App\\Domain\\CQRS\\Query';

        if ($this->option('query') && is_string($this->option('query'))) {
            // Check if the query is namespaced and if not, prepend the root namespace
            $class = str_contains($this->option('query'), '\\') ? $this->option('query') : $queryNamespace . '\\' . $this->option('query');
        } else {
            $class = $queryNamespace . '\\' . str_replace('Handler', '', $this->getNameInput());
        }

        $class = ltrim($class, '\\');

        if (!class_exists($class)) {
            throw new InvalidArgumentException("The specified query class '{$class}' does not exist.");
        }
        if (!is_a($class, QueryInterface::class, true)) {
            throw new InvalidArgumentException("The specified query class '{$class}' does not implement QueryInterface.");
        }

        return $class;
    }
}
