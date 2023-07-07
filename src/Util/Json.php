<?php

declare(strict_types=1);

namespace Keven\Pipe\Util;

function is_json(string $str): bool
{
    $j = json_decode($str);
    
    return !is_null($j) && json_last_error() === JSON_ERROR_NONE;
}
