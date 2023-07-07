<?php

declare(strict_types=1);

namespace Keven\Pipe\Plugin;

use Keven\Pipe\Pipe;

final class Merge
{
    public const
        TYPE_LEFT  = 'left',
        TYPE_RIGHT = 'right',
        TYPE_INNER = 'inner',
        TYPE_FULL  = 'full'
    ;

    private iterable $dataToMerge;
    private string $intoPropertyPath;
    private ?string $leftField, $rightField, $type;

    /**
     * @throws \Exception
     */
    public function __construct(iterable $dataToMerge, string $intoPropertyPath = null, string $leftField = null, string $rightField = null, string $type = self::TYPE_LEFT)
    {
        $this->dataToMerge = $dataToMerge;
        $this->intoPropertyPath ??= ($dataToMerge instanceof Pipe ? $dataToMerge->getLabel() : bin2hex(random_bytes(4)));
        $this->leftField = $leftField;
        $this->rightField = $rightField;
        $this->type = $type;
    }

    public function __invoke(iterable $source): array
    {
        $result = is_array($source) ? $source : iterator_to_array($source);
        foreach ($data as $key => $item) {
            $result[$this->accessor->get($item, $intoPropertyPath)] = $item;
        }
    }
}
