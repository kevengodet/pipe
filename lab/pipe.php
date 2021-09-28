<?php

use Keven\Pipe\Pipe;

require_once __DIR__.'/bootstrap.php';

(new Pipe(range(1, 10)))

    // Multiply by 3 easy...)
    ->map(function($n){ return $n * 3; })

    // Keep odd numbers
    ->keep(function($n) { return $n % 2 == 0;  })

    // Print remaining numbers
    ->exec(function($v) {
        echo $v.' ';
    })

    ->run();

echo "\n";

// Output: 6 12 18 24 30
