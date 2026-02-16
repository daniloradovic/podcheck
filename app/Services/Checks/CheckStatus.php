<?php

declare(strict_types=1);

namespace App\Services\Checks;

enum CheckStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
}
