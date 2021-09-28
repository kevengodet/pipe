<?php

use Keven\Pipe\Pipe;

require_once __DIR__.'/bootstrap.php';

print_r((new Pipe(get_defined_functions()['internal']))
        ->strToUpper()
        ->substr(0, 10)
        ->toArray());