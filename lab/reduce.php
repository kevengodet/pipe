<?php

use Keven\Pipe\Pipe;

require_once __DIR__.'/bootstrap.php';

echo (new Pipe(get_defined_functions()['internal']))
        ->strToUpper()
        ->reduce(function($nb, $name) { echo"$nb - $name\n"; return $nb + strlen($name); })."\n";