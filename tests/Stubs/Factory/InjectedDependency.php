<?php

declare(strict_types=1);

namespace Tests\Stubs\Factory;

final readonly class InjectedDependency
{
    public function __construct(
        public string $value,
    ) {}
}
