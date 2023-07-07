<?php

declare(strict_types=1);

namespace Keven\Pipe\Util;

final class Fold
{
    /**
     *
     * @var array
     */
    private $arguments;

    /**
     *
     * @param mixed $argument1
     * @param mixed $argument2
     * ...
     */
    public function __construct(/* $arg1, $arg2, ...*/)
    {
        if (func_num_args() == 1 and is_array(func_get_arg(0))) {
            $this->arguments = func_get_arg(0);
        } else {
            $this->arguments = func_get_args();
        }
    }

    /**
     *
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }
}
