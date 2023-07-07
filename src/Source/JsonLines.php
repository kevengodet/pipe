<?php

declare(strict_types=1);

namespace Keven\Pipe\Source;

use function Keven\Pipe\Util\{is_json, file_extension, file_first_line, file_lines};

/**
 * @see https://jsonlines.org/
 */
trait JsonLines
{
    private static function isJsonLinesSource($source): bool
    {
        // File path source
        if (is_string($source) && is_readable($source) && file_extension($source) === 'jsonl') {
            return true;
        }

        return is_json(file_first_line($source));
    }

    /**
     * @param $data resource|string|\SplFileObject
     * @param callable $transformer A callable to transform array of data to anything else (an entity class for example)
     * 
     * @return array|mixed
     * 
     * @throws Exception
     */
    public static function fromJsonLines($data, callable $transformer = null): self
    {
        if (!self::isJsonLinesSource($data)) {
            throw new \InvalidArgumentException('Invalid Json Lines input format.');
        }

        $unmarshaller = function (string $json, callable $transformer = null): mixed
        {
            $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    
            return is_null($transformer) ? $array : $transformer($array);
        };

        $iterator = function(iterable $source, callable $transformer = null) use ($unmarshaller) : iterable
        {
            foreach ($source as $json) {
                yield $unmarshaller($json, $transformer);
            }
        };

        if (is_array($data)) {
            return new self($iterator($data, $transformer));
        }

        return new self($iterator(file_lines($data), $transformer));
    }
}
