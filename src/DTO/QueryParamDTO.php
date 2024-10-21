<?php

declare(strict_types=1);

namespace Inilim\IPDO\DTO;

use Inilim\IPDO\ByteParamDTO;
use Inilim\IPDO\DTO\ByteParamDTO as DTOByteParamDTO;
use InvalidArgumentException;

/**
 * @psalm-mutation-free
 * @psalm-type Param   = string|null|int|float|bool|ByteParamDTO
 * @psalm-type ParamIN = string|int|float|bool|ByteParamDTO
 * @internal
 */
final class QueryParamDTO
{
    protected const PATTERN       = '#\{([a-z0-9\_]+)\}#i';
    protected static ?int $num    = null;
    protected static ?string $rnd = null;

    public string $query;
    /**
     * @var array<string,Param|ParamIN[]>
     */
    public array $values;

    /**
     * @param array<string,Param|ParamIN[]> $values
     */
    function __construct(
        string $query,
        array $values
    ) {
        $this->query = $query;
        $holes = [];
        \preg_match_all(self::PATTERN, $query, $holes);
        $holes = $holes[1] ?? [];
        /** @var string[] $holes */
        $hasHoles = !!$holes;
        unset($query);

        // ---------------------------------------------
        // 
        // ---------------------------------------------

        if (!$hasHoles) {
            // TODO стоит ли ругатся на переданные значения без дырок?
            if ($values) {
                throw new InvalidArgumentException(\sprintf(
                    'IPDO: 0',
                ));
            }
            $this->values = [];
            return;
        }

        // !нужные ключи запроса в values
        // !ненужные ключи в values
        // !дубли дырки
        // !переименование всех ключей и дырок
        // !что если в значении IN будут обьекты
        // !обработка значений со списком для оператора IN
        // !исключить многомерность значений в IN значениях
        // !в массиве для IN не должно быть null

        $this->values = $values;
        unset($values);

        $holes = \array_count_values($holes);
        /** @var array<string,int> $holes */

        // ---------------------------------------------
        // INFO берем только те ключи что есть в запросе
        // ---------------------------------------------

        $sizeBefore = \sizeof($this->values);
        $this->values = \array_intersect_key($this->values, $holes);
        $sizeAfter = \sizeof($this->values);

        // TODO стоит ли ругатся на лишние значения?
        if ($sizeBefore !== $sizeAfter) {
            throw new InvalidArgumentException(\sprintf(
                'IPDO: 1',
            ));
        }
        unset($sizeBefore);

        if (!$this->values) {
            throw new InvalidArgumentException(\sprintf(
                'IPDO: 2',
            ));
        }

        // ---------------------------------------------
        // INFO проверям что ключи из запроса есть в values
        // ---------------------------------------------

        if ($sizeAfter !== \sizeof($holes)) {
            throw new InvalidArgumentException(\sprintf(
                'IPDO: 3',
            ));
        }

        // ---------------------------------------------
        // INFO переименовываем и заменяем дубли
        // ---------------------------------------------

        foreach ($holes as $name => $repeat) {
            // INFO переименовка дублей
            if ($repeat > 1) {
                for ($i = 0; $i < $repeat; $i++) {
                    // INFO валидируем обьекты
                    if (\is_object($this->values[$name])) {
                        if (!($this->values[$name] instanceof DTOByteParamDTO)) {
                            throw new InvalidArgumentException(\sprintf(
                                'IPDO: 3.1',
                            ));
                        }
                        $newName = $this->getNewName();
                        $this->values[$newName] = clone $this->values[$name];
                    }
                    // INFO тут же обрабатываем массив значений
                    elseif (\is_array($this->values[$name])) {
                        $this->prepareSubValueArrayToInOperator($name);
                        continue; // continue чтобы не выполнить нижний replaceFirst
                    } else {
                        $newName = $this->getNewName();
                        $this->values[$newName] = $this->values[$name];
                    }

                    $this->query = $this->replaceFirst('{' . $name . '}', ' :' . $newName . ' ', $this->query);
                } // endfor
                unset($this->values[$name]);
            } // end repeat
            // INFO переименовка
            else {
                // INFO тут же обрабатываем массив значений
                if (\is_array($this->values[$name])) {
                    $this->prepareSubValueArrayToInOperator($name);
                } else {
                    $newName = $this->getNewName();
                    // INFO валидируем обьекты
                    if (\is_object($this->values[$name])) {
                        if (!($this->values[$name] instanceof DTOByteParamDTO)) {
                            throw new InvalidArgumentException(\sprintf(
                                'IPDO: 3.2',
                            ));
                        }
                        $this->values[$newName] = clone $this->values[$name];
                    } else {
                        $this->values[$newName] = $this->values[$name];
                    }
                    $this->query = \str_replace('{' . $name . '}', ' :' . $newName . ' ', $this->query);
                }
                unset($this->values[$name]);
            }
        } // endforeach
        // unset($newName, $name, $repeat, $i);
    }

    protected function prepareSubValueArrayToInOperator(string $oldName)
    {
        if ($this->isMultidimensional($this->values[$oldName])) {
            throw new InvalidArgumentException(\sprintf(
                'IPDO: 4',
            ));
        }
        $newHoles = [];
        foreach ($this->values[$oldName] as $subValue) {
            if ($subValue === null) {
                throw new InvalidArgumentException(\sprintf(
                    'IPDO: 5',
                ));
            }
            $newName = $this->getNewName();
            $newHoles[] = ' :' . $newName;
            if (\is_object($subValue)) {
                if (!($subValue instanceof DTOByteParamDTO)) {
                    throw new InvalidArgumentException(\sprintf(
                        'IPDO: 6',
                    ));
                }
                $this->values[$newName] = clone $subValue;
            } else {
                $this->values[$newName] = $subValue;
            }
        } // endforeach
        $this->query = $this->replaceFirst('{' . $oldName . '}', ' ( ' . \implode(',', $newHoles) . ' ) ', $this->query);
    }

    protected function getNewName(): string
    {
        self::$num ??= \mt_rand(100, 999);
        self::$rnd ??= \bin2hex(\random_bytes(2));
        return self::$rnd . '_' . ++self::$num;
    }

    /**
     * @param mixed[] $arr
     */
    protected function isMultidimensional(array $array): bool
    {
        // not forking if array = "[1, [], 3, 4, 5]"
        // return (\sizeof($arr) - \sizeof($arr, \COUNT_RECURSIVE)) !== 0;

        // \rsort($arr);
        // return isset($arr[0]) && \is_array($arr[0]);

        return \sizeof(\array_filter($array, 'is_array')) > 0;
    }

    protected function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') return $subject;
        $position = \strpos($subject, $search);
        if ($position !== false) {
            return \substr_replace($subject, $replace, $position, \strlen($search));
        }
        return $subject;
    }
}
