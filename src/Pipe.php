<?php

declare(strict_types=1);

namespace Keven\Pipe;

use ArrayIterator;
use Keven\Pipe\Plugin\Merge;
use Keven\Pipe\Util\Comparator;
use Keven\Pipe\Util\Fold;
use DateTime;
use DateTimeInterface;
use Exception;
use Generator;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use Keven\Pipe\Source\JsonLines;
use Keven\PropertyAccess\Accessor;
use Keven\PropertyAccess\Reader\ReaderInterface;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use OutOfBoundsException;
use ReflectionClass;
use ReflectionException;

class Pipe implements \IteratorAggregate, \Countable
{
    use JsonLines;

    /**
     *
     * @var iterable
     */
    private iterable $source;
    private string $label;
    private ReaderInterface $reader;

    /**
     *
     * @param iterable $source
     * @param Reader|null $reader
     * 
     * @throws Exception
     */
    public function __construct(iterable $source, ReaderInterface $reader = null)
    {
        $this->source = $this->toIterable($source);
        $this->reader = $reader ?? new Accessor;

        if ($source instanceof self && isset($source->label)) {
            $this->label = $source->label;
        }
    }

    /**
     * @throws Exception
     */
    public static function create($source): self
    {
        if ($source instanceof self) {
            return $source;
        }

        if (self::isJsonLinesSource($source)) {
            return self::fromJsonLines($source);
        }

        return new self($source);
    }

    public function label(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? spl_object_hash($this);
    }

    /**
     *
     * @param callable $gen
     *
     * @return Pipe
     * @throws Exception
     */
    private function wrap(callable $gen): Pipe
    {
        $pipe = new Pipe($gen($this->source));
        if (isset($this->label)) {
            $pipe->label($this->label);
        }

        return $pipe;
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
            $result = call_user_func_array($callable, $value->getArguments());

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
     * @throws Exception
     */
    public function filter(callable $callable): self
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
     * @throws Exception
     */
    public function map(callable $callable): self
    {
        return $this->wrap(
            function ($source) use ($callable) {
                $data = [];
                foreach ($source as $k => $v) {
                    $data[$k] = $v;
                    yield $k => $this->call($callable, $k, $v);
                }
                $this->source = $data;
            });
    }

    /**
     * Only keep items for which the callable returns true.
     *
     * @param callable $callable
     *
     * @return Pipe
     * @throws Exception
     */
    public function keep(callable $callable): self
    {
        return $this->wrap(
            function ($source) use ($callable) {
                foreach ($source as $k => $v) {
                    if (!$this->call($callable, $k, $v)) {
                        continue;
                    }

                    yield $k => $v;
                }
            }
        );
    }

    /**
     * Only keep items for which the callable returns true.
     *
     * @param string $propertyPath
     * @param $value
     * @param string $comparator
     * @return Pipe
     * @throws Exception
     */
    public function keepWhere(string $propertyPath, $value, string $comparator = Comparator::OP_EQUAL): self
    {
        return $this->keep(new Comparator($propertyPath, $value, $comparator));
    }

    /**
     * Drop items for which the callable returns true.
     *
     * @param callable $callable
     *
     * @return Pipe
     * @throws Exception
     */
    public function drop(callable $callable): self
    {
        return $this->wrap(
            function ($source) use ($callable) {
                foreach ($source as $k => $v) {
                    if ($this->call($callable, $k, $v)) {
                        continue;
                    }

                    yield $k => $v;
                }
            }
        );
    }

    /**
     * @throws Exception
     */
    public function dropWhere(string $propertyPath, $value, string $comparator = Comparator::OP_EQUAL): self
    {
        return $this->drop(new Comparator($propertyPath, $value, $comparator));
    }

    /**
     * Add items at the beginning of the collection.
     *
     * @param iterable $iterables
     *
     * @return Pipe
     * @throws Exception
     */
    public function prepend(...$iterables): self
    {
        $it = new \AppendIterator;
        foreach ($iterables as $iterable) {
            $it->append($this->toIterable($iterable));
        }
        $it->append($this->source);

        return new Pipe($it);
    }

    /**
     * Add items at the end of the collection.
     *
     * @param iterable $iterables
     *
     * @return Pipe
     * @throws Exception
     */
    public function append(...$iterables): self
    {
        $it = new \AppendIterator;
        $it->append($this->source);
        foreach ($iterables as $iterable) {
            $it->append($this->toIterable($iterable));
        }

        return new Pipe($it);
    }

    /**
     * Limit the number of items to process.
     *
     * @param int $n
     *
     * @return Pipe
     * @throws Exception
     */
    public function limit(int $n): self
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
     * @throws Exception
     */
    public function intersectKey(...$sources): self
    {
        $iterator = new \AppendIterator;
        $iterator->append($this->getIterator());
        foreach ($sources as $source) {
            $pipe = self::create($source);
            $iterator->append($pipe->getIterator());
        }
        $nbSources = count($sources) + 1;

        return $this->wrap(
            function () use ($iterator, $nbSources) {
                $keys = [];
                foreach ($iterator as $k => $v) {
                    $keys[$k][] = $v;
                    if (count($keys[$k]) === $nbSources) {
                        $value = $keys[$k][0];
                        unset($keys[$k]);

                        yield $k => $value;
                    }
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
     * @throws Exception
     */
    public function exec(callable $callable): self
    {
        return $this->wrap(
            function ($source) use ($callable) {
                foreach ($source as $k => $v) {
                    $this->call($callable, $k, $v);

                    yield $k => $v;
                }
            }
        );
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
            $initialValue = $callable($initialValue, $v, $k);
        }

        return $initialValue;
    }

    /**
     *
     * @param callable $callable
     *
     * @return Pipe
     */
    public function dispatch(callable $callable): self
    {
        return $callable($this);
    }

    /**
     *
     * @return array
     */
    public function toArray(): array
    {
        if (is_array($this->source)) {
            return $this->source;
        }

        // Work around non-rewindable generators
        return $this->source = iterator_to_array($this);
    }

    /**
     *
     * @param iterable $iterable
     *
     * @return iterable
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function toIterable($iterable): iterable
    {
        if ($iterable instanceof Iterator) {
            return $iterable;
        }

        if ($iterable instanceof IteratorAggregate) {
            return $iterable->getIterator();
        }

        if (is_array($iterable)) {
            return new ArrayIterator($iterable);
        }

        throw new InvalidArgumentException('Argument must be iterable');
    }

    /**
     * Run the process in the pipe, and track the progress.
     *
     * @param callable|null $progress
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
     * @throws ReflectionException
     * @throws Exception
     */
    public function cast(string $toClass): self
    {
        return $this->wrap(
            function ($source) use ($toClass) {
                $classReflection = new ReflectionClass($toClass);
                foreach ($source as $item) {
                    $object = $classReflection->newInstanceWithoutConstructor();
                    foreach ($item as $name => $value) {
                        $property = $classReflection->getProperty($name);
                        $property->setAccessible(true);
                        if (null === $value) {
                            $property->setValue($object, null);
                            continue;
                        }
                        if ($property->getType()->getName() === DateTimeInterface::class) {
                            $property->setValue($object, new DateTime($value));
                        } else {
                            $fn = $property->getType()->getName() === 'string' ? 'strval' : 'intval';
                            $property->setValue($object, $fn($value));
                        }
                    }

                    yield $object;
                }
            }
        );
    }

    /**
     *
     * @param string $name
     * @param array $arguments
     *
     * @return Pipe
     * @throws Exception
     */
    public function __call(string $name, array $arguments)
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

        return $this;
    }

    // IteratorAggregate implementation

    /**
     *
     * @return Generator
     */
    public function getIterator(): Generator
    {
        foreach ($this->source as $k => $v) {
            yield $k => $v;
        }
    }

    /**
     * @throws CsvException
     * @throws Exception
     */
    public static function fromCsv($data): self
    {
        if ($data instanceof Reader) {
            $csv = $data;
        } elseif(is_resource($data) && get_resource_type($data) === 'stream') {
            $csv = Reader::createFromStream($data);
        } elseif($data instanceof \SplFileObject) {
            $csv = Reader::createFromFileObject($data);
        } elseif (is_string($data) && is_readable($data)) {
            $csv = Reader::createFromPath($data);
        } elseif (is_string($data)) {
            $csv = Reader::createFromString($data);
        } else {
            throw new InvalidArgumentException('Invalid data type for CSV source');
        }

        $csv->setHeaderOffset(0);

        return new self($csv);
    }

    public function __toString(): string
    {
        $data = $this->toArray();

        return implode("\n", $data);
    }

    public function print(): self
    {
        print_r($this->toArray());

        return $this;
    }

    public function dump(): self
    {
        var_dump($this->toArray());

        return $this;
    }

    /**
     * @throws Exception
     */
    public function key($property): self
    {
        return $this->wrap(
            function ($source) use ($property) {
                foreach ($source as $item) {
                    if (is_callable($property)) {
                        yield $property($item) => $item;
                    } else {
                        yield $this->reader->read($item, $property) => $item;
                    }
                }
            }
        );
    }

    /**
     * @param $key
     * @return mixed|void|null
     * @throws OutOfBoundsException
     */
    public function get($key)
    {
        if (is_array($this->source)) {
            if (!isset($this->source[$key])) {
                throw new OutOfBoundsException("Key '$key' not found");
            }

            return $this->source[$key];
        }

        foreach ($this->source as $k => $value) {
            if ($k === $key) {
                return $value;
            }
        }

        throw new OutOfBoundsException("Key '$key' not found");
    }

    public function getMaybe($key)
    {
        try {
            return $this->get($key);
        } catch (OutOfBoundsException $e) {
            return null;
        }
    }

    /**
     * @throws Exception
     */
    public function zip(...$sources): self
    {
        return $this->wrap(
            function ($currentSource) use ($sources) {
                $sources = array_map(__CLASS__.'::create', $sources);
                foreach ($currentSource as $k => $v) {
                    $data = [$v];
                    foreach ($sources as $sourceName => $otherSource) {
                        $data[$sourceName] = $otherSource->get($k);
                    }
                    yield $k => $data;
                }
            }
        );
    }

    /**
     * @throws Exception
     */
    public function project($property): self
    {
        return $this->wrap(
            function ($source) use ($property) {
                $data = [];
                foreach ($source as $item) {
                    if (is_callable($property)) {
                        $data[$property($item)][] = $item;
                    } else {
                        $data[$this->reader->read($item, $property)][] = $item;
                    }
                }

                return $data;
            }
        );
    }

    /**
     * @throws Exception
     */
    public function pluck($propertyPath): self
    {
        return $this->wrap(
            function ($source) use ($propertyPath) {
                $data = [];
                foreach ($source as $key => $item) {
                    yield $key => $this->reader->read($item, $propertyPath);
                    $data[$key] = $item;
                }

                // Work around non-rewindable generators
                $this->source = $data;
            }
        );
    }

    /**
     * @param array $sources
     * @param string $type Type : LEFT, INNER, RIGHT, FULL
     * @return $this
     * @throws Exception
     */
    public function join(array $sources, string $type = 'INNER'): self
    {
        return $this->wrap(
            function ($currentSource) use ($sources, $type) {
                $result = [];
                $keys = [];
                $joinProperties = array_keys($sources);
                foreach ($currentSource as $key => $item) {
                    $result[$key][$this->getLabel()] = $item;
                    foreach ($joinProperties as $joinProperty) {
                        $keys[$joinProperty][$this->reader->read($item, $joinProperty)] = $key;
                    }
                }

                /* @var $otherSource Pipe */
                foreach ($sources as $propertyJoin => $otherSource) {
//                    $saneSource = self::create($otherSource)->key($propertyJoin);
                    $sourceLabel = $otherSource->getLabel();
                    foreach ($otherSource as $key => $item) {

                        if (!is_int($propertyJoin) && !isset($keys[$propertyJoin][$key])) {
                            continue;
                        }

                        $index = is_int($propertyJoin) ? $key : $keys[$propertyJoin][$key];

                        $result[$index][$sourceLabel] = $item;
                    }
                }

                if ($type === 'FULL') {
                    return $result;
                }

                if ($type === 'INNER') {
                    $nbSources = count($sources) + 1;
                    foreach ($result as $key => $item) {
                        if (count($item) === $nbSources) {
                            yield $key => $item;
                        }
                    }
                } elseif ($type === 'LEFT') {
                    $sourceLabel = $this->getLabel();
                    foreach ($result as $key => $item) {
                        if (isset($item[$sourceLabel])) {
                            yield $key => $item;
                        }
                    }
                } else {
                    throw new InvalidArgumentException("Invalid join type '$type'");
                }
            }
        );
    }

    /**
     * @throws Exception
     */
    public function merge(iterable $data, string $intoPropertyPath = null, string $leftField = null, string $rightField = null, string $type = 'LEFT'): self
    {
        return $this->wrap(new Merge($data, $intoPropertyPath, $leftField, $rightField, $type));
    }

    public function count(): int
    {
        return iterator_count($this);
    }
}
