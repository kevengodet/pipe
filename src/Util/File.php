<?php

declare(strict_types=1);

namespace Keven\Pipe\Util;

function file_extension(string $filePath): string
{
    return pathinfo($filePath, PATHINFO_EXTENSION);
}

/**
 * @param \SplObjectInfo|\SplFileInfo|string|resource $file
 * @throw \InvalidArgumentException
 */
function file_first_line($file): string
{
    if ($file instanceof \SplFileInfo) {
        return file_first_line($file->getRealPath());
    }

    if (is_resource($file)) {
        if (get_resource_type($file) !== 'stream') {
            throw new \InvalidArgumentException("Resource must be of type 'stream'.");
        }

        if (false === $pos = ftell($file)) {
            throw new \InvalidArgumentException('Cannot ftell on stream resource.');
        }

        if ($pos > 0 && -1 === fseek($file, 0)) {
            throw new \InvalidArgumentException('Cannot seek through stream resource.');
        }

        if (false === $line = fgets($file)) {
            throw new \InvalidArgumentException('Cannot read through stream resource.');
        }

        // Get back to the initial position
        fseek($file, $pos);

        return $line;
    }

    if (is_string($file) && is_readable($file)) {
        if (false === $fh = fopen($file, 'r')) {
            throw new \InvalidArgumentException("Cannot open '$file' to read.");
        }

        try {
            return file_first_line($fh);
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            fclose($fh);
        }
    }

    if (!is_string($file)) {
        throw new \InvalidArgumentException('Unsupported source type. Accepted types: file path, file resource, string, instance of SplObjectInfo/SplFileInfo');
    }

    $lineFeedPos = strpos($file, "\n");

    // If there is no line feed, return the full string
    if (false == $lineFeedPos) {
        return $file;
    }

    return substr($file, 0, $lineFeedPos);
}

function file_lines($file): iterable
{
    if ($file instanceof \SplFileInfo) {
        return file_first_line($file->getRealPath());
    }

    if (is_string($file) && is_readable($file)) {
        if (false === $fh = fopen($file, 'r')) {
            throw new \InvalidArgumentException("Cannot open '$file' to read.");
        }

        try {
            foreach (file_lines($fh) as $line) {
                yield $line;
            }
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            fclose($fh);
        }

        return;
    }

    if (is_resource($file)) {
        if (get_resource_type($file) !== 'stream') {
            throw new \InvalidArgumentException("Resource must be of type 'stream'.");
        }

        while (!feof($file)) {
            $line = fgets($file);

            if (false !== $line) {
                yield $line;
            }
        }

        return;
    }

    if (!is_string($file)) {
        throw new \InvalidArgumentException('Unsupported source type. Accepted types: file path, file resource, string, instance of SplObjectInfo/SplFileInfo');
    }

    // \R = [ \r\n, \r, \n ]
    return preg_split ('/\R/', $file);
}
