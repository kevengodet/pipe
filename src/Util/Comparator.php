<?php

declare(strict_types=1);

namespace Keven\Pipe\Util;

use Keven\PropertyAccess\Accessor\AccessorInterface;
use Keven\PropertyAccess\Accessor\ChainAccessor;

final class Comparator
{
    public const
        OP_EQUAL = '=',
        OP_IN = 'IN'
    ;

    private string $propertyPath, $comparator;
    private $value;

    private AccessorInterface $accessor;

    public function __construct(string $propertyPath, $value, string $comparator = self::OP_EQUAL, AccessorInterface $accessor = null)
    {
        $this->propertyPath = $propertyPath;
        $this->value = $value;
        $this->comparator = $comparator;
        $this->accessor = $accessor ?? ChainAccessor::getInstance();
    }

    public function __invoke($data): bool
    {
        switch ($this->comparator) {
            case self::OP_IN:
                return in_array($this->accessor->get($data, $this->propertyPath), $this->value, true);
            case self::OP_EQUAL:
                return $this->accessor->get($data, $this->propertyPath) == $this->value;
            case '!=':
                return $this->accessor->get($data, $this->propertyPath) != $this->value;
            case '>':
                return $this->accessor->get($data, $this->propertyPath) >   $this->value;
            case '<':
                return $this->accessor->get($data, $this->propertyPath) <   $this->value;
            case '>=':
                return $this->accessor->get($data, $this->propertyPath) >=  $this->value;
            case '<=':
                return $this->accessor->get($data, $this->propertyPath) <=  $this->value;
            default:
                throw new \InvalidArgumentException('Invalid comparator');
        }
    }
}
