<?php

use Keven\Pipe\Pipe;
use Keven\Pipe\Fold;

require_once __DIR__.'/bootstrap.php';

(new Pipe(range(1, 10)))

        ->map(function($v) {
            return new Fold($v, rand(1, 100));
        })

        // Fold a random number with the int from the range
        ->exec(function($v, $rnd) {
            echo $v.' / '.$rnd."\n";
        })

        // The folded arguments are transmitted to the next operations:
        ->map(function($v, $rnd) {
            echo $v.' & '.(2*$rnd)."\n";

            return [$v, 2 * $rnd];

            // or: return new Fold($v, 2 * $rnd);
        })

        // You can unfold one or several parameters
        ->map(function($v, $rnd) {
            return $rnd;
        })

        // Only the random value persist
        ->exec(function($v) {
            echo "Rnd: $v\n";
        })

        ->run();
