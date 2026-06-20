<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Fixtures;

enum ItemStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
    case Draft    = 'draft';
}
