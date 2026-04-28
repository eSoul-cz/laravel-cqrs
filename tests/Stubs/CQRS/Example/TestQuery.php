<?php

declare(strict_types=1);

namespace Tests\Stubs\CQRS\Example;

use Esoul\Cqrs\Contracts\QueryInterface;

/**
 * @implements QueryInterface<string>
 */
class TestQuery implements QueryInterface {}
