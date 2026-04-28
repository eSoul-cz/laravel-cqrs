<?php

declare(strict_types=1);

namespace Esoul\LaravelCqrs\Console\Commands;

use Esoul\Cqrs\Contracts\CommandInterface;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('make:cqrs-command-handler')]
class CommandHandlerMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:cqrs-command-handler {name} {--command= : Command for which to create the handler} {--force : Overwrite existing files} {--return= : Return type of the handler method}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a CQRS command handler class';

    protected $type = 'CommandHandler';

    public function handle(): ?bool
    {
        try {
            return parent::handle();
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
        return dirname(__DIR__) . '/stubs/commandHandler.stub';
    }

    protected function rootNamespace(): string
    {
        $namespace = config('cqrs.command_handlers.base_namespace');

        return is_string($namespace) && $namespace !== ''
            ? trim($namespace, '\\')
            : 'App\\Domain\\CQRS\\Command';
    }

    protected function getPath($name): string
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        $path = config('cqrs.command_handlers.path');
        $basePath = is_string($path) && $path !== ''
            ? rtrim($path, DIRECTORY_SEPARATOR)
            : app_path('Domain/CQRS/Command');

        return $basePath . str_replace('\\', '/', $name) . '.php';
    }

    protected function getNameInput(): string
    {
        $name = parent::getNameInput();
        if (str_ends_with($name, 'Handler')) {
            return $name;
        }
        if (str_ends_with($name, 'Command')) {
            return $name . 'Handler';
        }

        return $name . 'CommandHandler';
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

        $commandName = $this->getFullCommandName();

        // Strip namespace from command
        $classname = $commandName;
        if (preg_match('/\\\\([\w]+)$/', $classname, $matches)) {
            $classname = $matches[1];
        }

        $replacements['{{ commandUse }}'] = 'use ' . $commandName . ';';
        $replacements['{{ command }}'] = ltrim($classname, '\\');

        $return = $this->option('return');
        $replacements['{{ return }}'] = $return && is_string($return) ? $return : 'mixed';

        return $replacements;
    }

    /**
     * @return class-string<CommandInterface<mixed>>
     */
    private function getFullCommandName(): string
    {
        $commandNamespace = config('cqrs.commands.base_namespace');
        $commandNamespace = is_string($commandNamespace) && $commandNamespace !== ''
            ? trim($commandNamespace, '\\')
            : 'App\\Domain\\CQRS\\Command';

        if ($this->option('command') && is_string($this->option('command'))) {
            // Check if the command is namespaced and if not, prepend the root namespace
            $class = str_contains($this->option('command'), '\\') ? $this->option('command') : $commandNamespace . '\\' . $this->option('command');
        } else {
            $class = $commandNamespace . '\\' . str_replace('Handler', '', $this->getNameInput());
        }

        $class = ltrim($class, '\\');

        if (!class_exists($class)) {
            throw new InvalidArgumentException("The specified command class '{$class}' does not exist.");
        }
        if (!is_a($class, CommandInterface::class, true)) {
            throw new InvalidArgumentException("The specified command class '{$class}' does not implement CommandInterface.");
        }

        return $class;
    }
}
