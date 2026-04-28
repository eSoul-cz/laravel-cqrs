<?php

declare(strict_types=1);

namespace Tests\Stubs\Factory;

final readonly class ConstructedWithUnresolvableDependency
{
    public function __construct(
        public UnresolvableDependency $dependency,
    ) {}
}
