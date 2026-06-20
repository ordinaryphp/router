<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Fixtures;

/**
 * Zero-case backed enum used to test the empty-enum guard in ParamBuilder::enum().
 */
enum EmptyEnum: string {}
