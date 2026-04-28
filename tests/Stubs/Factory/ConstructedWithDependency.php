<?php

declare(strict_types=1);

namespace Tests\Stubs\Factory;

final readonly class ConstructedWithDependency
{
    public function __construct(
        public InjectedDependency $dependency,
    ) {}
}
