Usage
=====

A collection processing pipe. Methods are chainable.

Each step of the pipe is executed only if required: if you filter out 90% of the
items at one step, then further steps will only process the remaining 10%.

You should always use `yield` in your callables to avoid having the whole collection
in memory.

You can test the example below by running `php lab/example.php` from the root of
the component.

```php
<?php

// Accepts any iterable, as defined by PHP 7.1 (array, Iterator)
(new Pipe(range(0, 10)))

    // Pass the entire collections through the callable to filter (add/remove) items
    ->filter(function($numbers) {
        foreach ($numbers as $number) {

            // Discard zeros
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

    // Discard items for which the callable returns true
    ->discard(function($letter) {
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

// Output: ABCDEFGHIJKLMNOPQRSTUVWXYZ

echo "\n";

// ...or calling toArray()
$items = $pipe->toArray();
print_r($items);

// Output:
// Array
// (
//     [0] => a
//     [1] => b
//     [2] => c
//     [3] => d
//     [4] => e
//     [5] => f
//     [6] => g
//     [7] => h
//     [8] => i
//     [9] => j
//     [10] => k
//     [11] => l
//     [12] => m
//     [13] => n
//     [14] => o
//     [15] => p
//     [16] => q
//     [17] => r
//     [18] => s
//     [19] => t
//     [20] => u
//     [21] => v
//     [22] => w
//     [23] => x
//     [24] => y
//     [25] => z
// )
```
