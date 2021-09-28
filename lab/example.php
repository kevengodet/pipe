<?php

use Keven\Pipe\Pipe;
use Keven\Pipe\Fold;

require_once __DIR__.'/bootstrap.php';

// Accepts any iterable, as defined by PHP 7.1 (array, Iterator)
(new Pipe(range(0, 10)))

    // Pass the entire collections through the callable to filter (add/remove) items
    ->filter(function($numbers) {
        foreach ($numbers as $number) {

            // Drop zeros
            if (0 === $number) {
                continue;
            }

            yield $number;
        }

        // Add items
        foreach ([20, 50, 100] as $number) {
            yield $number;
        }
    })

    // Transform a single item
    ->map(function($number) {
        // We gonna replace the numbers by their equivalent letter in alphabet
        return chr(ord('a') + ($number - 1) % 26);
    })

    // Drop items for which the callable returns true
    ->drop(function($letter) {
        // Let say we keep only consonants
        return in_array($letter, ['a', 'e', 'i', 'o', 'u', 'y']);
    })

    // Only keep items for which the callable returns true
    ->keep(function($letter) {
        // Letters after W are weird to me, I must confess...
        return $letter < 'w';
    })

    // Add items at the end of the collection
    ->append(['l', 'b', 'c', 's', 't'])

    // Add items at the beginning of the collection
    ->prepend(['w', 't', 'f', 'b', 'b', 'q'])

    // Fold another argument for the next operations
    ->map(function($letter) {
        return new Fold($letter, strtoupper($letter));
    })

    // Make some operations on each item
    ->exec(function($letter, $upper) {
        // Wow, I am lost, let print all the letters
        echo $letter.$upper.' ';
    })

    // Output: wW tT fF bB bB qQ bB cC dD fF gG hH jJ tT vV lL bB cC sS tT

    // Limit the number of items to process
    ->limit(10)

    // Run the process in the pipe, and track the progress
    ->run(function($progress, $letter, $upper) {
        echo "Processed item #$progress: $letter/$upper...\n";
    });

echo "\n";

$pipe = new Pipe(range('a', 'z'));

// Instead of calling run(), you can also iterate over the pipe...
foreach ($pipe as $item) {
    echo strtoupper($item);
}

echo "\n";

// ...or calling toArray()...
$items = $pipe->toArray();
print_r($items);

// ...or reduce the data stream to a single value (to count vowels in a letter stream for example)
echo $pipe->reduce(function ($vowelCount, $letter) {
    return $vowelCount + (in_array($letter, ['a', 'e', 'i', 'o', 'u', 'y']) ? 1 : 0);
})."\n";

// Output is obviously: 6