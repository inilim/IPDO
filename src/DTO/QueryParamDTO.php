<?php

declare(strict_types=1);

namespace Inilim\IPDO\DTO;

use Inilim\IPDO\ByteParamDTO;
use Inilim\IPDO\Exception\IPDOException;

/**
 * @psalm-mutation-free
 * @internal
 */
final class QueryParamDTO
{
    /**
     * @var string
     */
    public $query;
    /**
     * @var string[]
     */
    public array $queryHoles;
    /**
     * @var array<string,string|null|int|float|bool|ByteParamDTO>
     */
    public array $values;
    /**
     * @var string[]
     */
    // public array $fieldNames;

    public bool $hasHoles;

    function __construct(
        string $query,
        array $values
    ) {
        $this->query    = $query;

        $this->hasHoles = \strpos($query, ':') !== false;

        $this->queryHoles = [];

        if ($this->hasHoles) {
            $holes = [];
            \preg_match_all('#\:([a-z0-9\_]+)#i', $query, $holes);
            // @phpstan-ignore-next-line
            $holes = $holes[1] ?? [];
            /** @var string[] $holes */
            if (!$holes) {
                $this->hasHoles = false;
            }
            $this->queryHoles = $holes;
            unset($holes);
        }

        // удаляем ненужные ключи/значения из массива $values
        if ($this->hasHoles) {
            $this->values = \array_intersect_key($values, \array_flip($this->queryHoles));
        } else {
            $this->values = $values;
        }

        // проверяем что имена дырок совпадает с именами ключей в $values
        $fieldNames = \array_keys($this->values);
        if (\array_diff($fieldNames, $this->queryHoles)) {
            throw new IPDOException(\sprintf(
                'there are more holes in the request than parameters. holes:"%s" | values:"%s"',
                \implode(',', $this->queryHoles),
                \implode(',', $fieldNames),
            ));
        }
        unset($fieldNames);

        // заменяем дубли дырок
        // if ($this->hasHoles && \sizeof($this->values) !== \sizeof($this->queryHoles)) {
        // foreach ($this->values as $fieldName => $value) {
        // }
        // }

        // $this->fieldNames = \array_keys($this->values);
    }
}
