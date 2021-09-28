<?php

namespace Keven\Pipe;

final class Pipe implements \IteratorAggregate
{
    /**
     *
     * @var iterable
     */
    private $source;

    /**
     *
     * @param iterable $source
     */
    public function __construct($source)
    {
        $this->source = $this->toIterable($source);
    }

    /**
     *
     * @param \Closure $gen
     *
     * @return Pipe
     */
    private function wrap($gen)
    {
        return new static($gen($this->source));
    }

    /**
     *
     * @param callable $callable
     * @param scalar $key
     * @param mixed $value
     *
     * @return mixed
     */
    private function call(callable $callable, $key, $value)
    {
        if ($value instanceof Fold) {
            $result = call_user_func_array($callable, $args = $value->getArguments());

            if (is_array($result)) {
                return new Fold($result);
            }

            return $result;
        }

        return $callable($value, $key);
    }

    /**
     * Pass the entire collections through the callable to filter (add/remove)
     * items.
     *
     * @param callable $callable
     *
     * @return Pipe
     */
    public function filter(callable $callable)
    {
        return $this->wrap(
            function ($source) use ($callable) {
                return $callable($source);
            }
        );
    }

    /**
     * Transform a single item
     *
     * @param callable $callable
     *
     * @return Pipe
     */
    public function map(callable $callable)
    {
        return $this->wrap(
            function ($source) use ($callable) {
                foreach ($source as $k => $v) {
                    yield $this->call($callable, $k, $v);
                }
            });
    }

    /**
     * Only keep items for which the callable returns true.
     *
     * @param callable $callable
     *
     * @return Pipe
     */
    public function keep(callable $callable)
    {
        return $this->wrap(
            function ($source) use ($callable) {
                foreach ($source as $k => $v) {
                    if (!$this->call($callable, $k, $v)) {
                        continue;
                    }

                    yield $k => $v;
                }
            });
    }

    /**
     * Drop items for which the callable returns true.
     *
     * @param callable $callable
     *
     * @return Pipe
     */
    public function drop(callable $callable)
    {
        return $this->wrap(
            function ($source) use ($callable) {
                foreach ($source as $k => $v) {
                    if ($this->call($callable, $k, $v)) {
                        continue;
                    }

                    yield $k => $v;
                }
            });
    }

    /**
     * Add items at the beginning of the collection.
     *
     * @param iterable $iterable
     *
     * @return Pipe
     */
    public function prepend($iterable/*, $iterable, ...*/)
    {
        $it = new \AppendIterator;
        foreach (func_get_args() as $iterable) {
            $it->append($this->toIterable($iterable));
        }
        $it->append($this->source);

        return new static($it);
    }

    /**
     * Add items at the end of the collection.
     *
     * @param iterable $iterable
     *
     * @return Pipe
     */
    public function append($iterable/*, $iterable, ...*/)
    {
        $it = new \AppendIterator;
        $it->append($this->source);
        foreach (func_get_args() as $iterable) {
            $it->append($this->toIterable($iterable));
        }

        return new static($it);
    }

    /**
     * Limit the number of items to process.
     *
     * @param int $n
     *
     * @return Pipe
     */
    public function limit($n)
    {
        return $this->wrap(
            function ($source) use ($n) {
                foreach ($source as $k => $v) {
                    if ($n-- <= 0) {
                        return;
                    }

                    yield $k => $v;
                }
            }
        );
    }

    /**
     * Make some operations on each item.
     *
     * @param callable $callable
     *
     * @return Pipe
     */
    public function exec(callable $callable)
    {
        return $this->wrap(
            function ($source) use ($callable) {
                foreach ($source as $k => $v) {
                    $this->call($callable, $k, $v);

                    yield $k => $v;
                }
            });
    }

    /**
     *
     * @param callable $callable
     * @param mixed $initialValue
     *
     * @return mixed
     */
    public function reduce(callable $callable, $initialValue = null)
    {
        foreach ($this->source as $k => $v) {
            $initialValue = call_user_func_array($callable, [$initialValue, $v, $k]);
        }

        return $initialValue;
    }

    /**
     *
     * @param callable $callable
     *
     * @return Pipe
     */
    public function dispatch(callable $callable)
    {
        return $callable($this);
    }

    /**
     *
     * @return array
     */
    public function toArray()
    {
        return iterator_to_array($this);
    }

    /**
     *
     * @param iterable $iterable
     *
     * @return iterable
     *
     * @throws \InvalidArgumentException
     */
    private function toIterable($iterable)
    {
        if ($iterable instanceof \Iterator) {
            return $iterable;
        }

        if ($iterable instanceof \IteratorAggregate) {
            return $iterable->getIterator();
        }

        if (is_array($iterable)) {
            return new \ArrayIterator($iterable);
        }

        throw new \InvalidArgumentException('Argument must be iterable');
    }

    /**
     * Run the process in the pipe, and track the progress.
     *
     * @param callable $progress
     */
    public function run(callable $progress = null)
    {
        $n = 1;
        foreach ($this->source as $k => $v) {
            if (!is_null($progress)) {
                if ($v instanceof Fold) {
                    $args = $v->getArguments();
                    array_unshift($args, $n);
                    $v = new Fold($args);
                } else {
                    $v = new Fold($n, $v);
                }
                $this->call($progress, $k, $v);
                $n++;
            }
        }
    }

    /**
     *
     * @param string $name
     * @param array $arguments
     *
     * @return Pipe
     */
    public function __call($name, array $arguments)
    {
        if (is_callable($name)) {
            return $this->map(function() use ($name, $arguments) {
                $args = func_get_args();

                // Drop last func_get_args() because it's the key
                $args = array_merge(array_slice($args, 0, -1), $arguments);
                $f = new \ReflectionFunction($name);
                $args = array_slice($args, 0, $f->getNumberOfParameters());

                return call_user_func_array($name, $args);
            });
        }
    }

    // IteratorAggregate implementation

    /**
     *
     * @return \Generator
     */
    public function getIterator()
    {
        foreach ($this->source as $k => $v) {
            yield $k => $v;
        }
    }
}
