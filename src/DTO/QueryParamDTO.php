<?php

declare(strict_types=1);

namespace Inilim\IPDO\DTO;

use Inilim\IPDO\ByteParamDTO;
use Inilim\IPDO\Exception\IPDOException;

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
            // if ($values) {
            //     throw new IPDOException([
            //         'message' => 'Invalid Argument: The values for the request were passed, but there are no holes in the request'
            //     ]);
            // }
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
        // в массиве для IN не должно быть null

        $this->values = $values;
        unset($values);

        $holes = \array_count_values($holes);
        /** @var array<string,int> $holes */

        // ---------------------------------------------
        // INFO берем только те ключи что есть в запросе
        // ---------------------------------------------

        // $sizeBefore = \sizeof($this->values);
        $this->values = \array_intersect_key($this->values, $holes);
        // $sizeAfter = \sizeof($this->values);

        // TODO стоит ли ругатся на лишние значения?
        // if ($sizeBefore !== $sizeAfter) {
        //     throw new IPDOException([
        //         'message' => \sprintf('Invalid Argument: 1'),
        //     ]);
        // }

        if (!$this->values) {
            throw new IPDOException([
                'message' => \sprintf('Invalid Argument: 2'),
            ]);
        }

        // ---------------------------------------------
        // INFO проверям что ключи из запроса есть в values
        // ---------------------------------------------

        if (\sizeof($this->values) !== \sizeof($holes)) {
            throw new IPDOException([
                'message' => \sprintf('Invalid Argument: 3'),
            ]);
        }

        // ---------------------------------------------
        // INFO переименовываем и заменяем дубли
        // ---------------------------------------------

        foreach ($holes as $name => $repeat) {
            // INFO переименовка дублей
            if ($repeat > 1) {
                for ($i = 0; $i < $repeat; $i++) {
                    if (\is_object($this->values[$name])) {
                        $newName = $this->getNewName();
                        $this->values[$newName] = clone $this->values[$name];
                    }
                    // INFO тут же обрабатываем массив значений
                    elseif (\is_array($this->values[$name])) {
                        if ($this->isMultidimensional($this->values[$name])) {
                            throw new IPDOException([
                                'message' => \sprintf('Invalid Argument: 4'),
                            ]);
                        }
                        $newHoles = [];
                        foreach ($this->values[$name] as $subValue) {
                            if ($subValue === null) {
                                throw new IPDOException([
                                    'message' => \sprintf('Invalid Argument: 5'),
                                ]);
                            }
                            $newName = $this->getNewName();
                            $newHoles[] = ' :' . $newName;
                            $this->values[$newName] = \is_object($subValue) ? clone $subValue : $subValue;
                        } // endforeach
                        $this->query = $this->replaceFirst('{' . $name . '}', ' ( ' . \implode(',', $newHoles) . ' ) ', $this->query);
                        unset($newHoles);
                        continue; // continue чтобы не выполнить нижний replaceFirst
                    } else {
                        $newName = $this->getNewName();
                        $this->values[$newName] = $this->values[$name];
                    }

                    $this->query = $this->replaceFirst('{' . $name . '}', ' :' . $newName . ' ', $this->query);
                } // endFor
                unset($this->values[$name]);
            }
            // INFO переименовка
            else {
                $newName = $this->getNewName();
                $this->values[$newName] = $this->values[$name];
                unset($this->values[$name]);
                $this->query = \str_replace('{' . $name . '}', ' :' . $newName . ' ', $this->query);
            }
        } // endforeach
        // unset($newName, $name, $repeat, $i);
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
    protected function isMultidimensional(array $arr): bool
    {
        return (\sizeof($arr) - \sizeof($arr, \COUNT_RECURSIVE)) !== 0;
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
