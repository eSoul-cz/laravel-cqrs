<?php

declare(strict_types=1);

namespace Esoul\LaravelCqrs\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

#[AsCommand('make:cqrs-command')]
class CommandMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:cqrs-command {name} {--handler : Create a command handler class for the command} {
        --return= : The return type of the command handler method} {
        --force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a CQRS command class';

    protected $type = 'Command';

    /**
     * Execute the console command.
     */
    public function handle(): bool
    {
        if (parent::handle() === false && !$this->option('force')) {
            if (!$this->alreadyExists($this->getNameInput())) {
                return false;
            }

            if (!confirm('Do you want to generate additional components for the command?')) {
                return false;
            }

            $this->afterPromptingForMissingArguments($this->input, $this->output);
        }

        if ($this->option('handler')) {
            $exitCode = $this->call('make:cqrs-command-handler', [
                'name' => $this->getNameInput(),
                '--command' => $this->qualifyClass($this->getNameInput()),
                '--force' => $this->option('force'),
                '--return' => $this->option('return'),
            ]);

            return $exitCode === self::SUCCESS;
        }

        return true;
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
        return dirname(__DIR__) . '/stubs/command.stub';
    }

    protected function rootNamespace(): string
    {
        $namespace = config('cqrs.commands.base_namespace');

        return is_string($namespace) && $namespace !== ''
            ? trim($namespace, '\\')
            : 'App\\Domain\\CQRS\\Command';
    }

    protected function getPath($name): string
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        $path = config('cqrs.commands.path');
        $basePath = is_string($path) && $path !== ''
            ? rtrim($path, DIRECTORY_SEPARATOR)
            : app_path('Domain/CQRS/Command');

        return $basePath . str_replace('\\', '/', $name) . '.php';
    }

    protected function getNameInput(): string
    {
        $name = parent::getNameInput();
        if (!str_ends_with($name, 'Command')) {
            $name .= 'Command';
        }

        return $name;
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

        if ($this->option('return') && is_string($this->option('return'))) {
            $replacements['{{ returnType }}'] = $this->option('return');
        } else {
            $replacements['{{ returnType }}'] = 'mixed';
        }

        return $replacements;
    }

    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        if ($this->didReceiveOptions($input) || $this->isReservedName($this->getNameInput())) {
            return;
        }

        new Collection(
            multiselect(
                'Would you like any of the following?',
                [
                    'handler' => 'Command handler',
                ]
            )
        )
            /** @phpstan-ignore argument.type */
            ->each(fn (string $option) => $input->setOption($option, true));
    }
}
