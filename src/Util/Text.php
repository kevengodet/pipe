<?php

declare(strict_types=1);

namespace Keven\Pipe\Util;

/**
 * Assert a parameter is a PHP resource type, or theow an exception
 * 
 * @param $resource Expected to be a `resource`
 * @param string $type An optional resource type to check against
 */
function assert_resource($resource, string $type = null): void
{
    if (false === is_resource($resource)) {
        throw new \InvalidArgumentException(
            sprintf(
                'Argument must be a valid resource type. %s given.',
                gettype($resource)
            )
        );
    }

    if ($type !== null &&  get_resource_type($resource) !== $type) {
        throw new \InvalidArgumentException(
            sprintf(
                "Resource must be of type '%s'. '%s' given.",
                $type,
                get_resource_type($resource)
            )
        );
    }
}
