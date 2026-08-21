<?php

declare(strict_types=1);

namespace Inilim\IPDO\DTO;

use Inilim\IPDO\DTO\ByteParamDTO;
use Inilim\IPDO\Util;
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
     * @throws InvalidArgumentException
     */
    function __construct(
        string $query,
        array $values
    ) {
        $this->query = $query;
        $holes = [];
        \preg_match_all(self::PATTERN, $query, $holes);
        $holes = $holes[1];
        /** @var list<non-empty-string> $holes */
        $hasHoles = !!$holes;
        unset($query);

        // ---------------------------------------------
        // 
        // ---------------------------------------------

        if (!$hasHoles) {
            if ($values) {
                throw new InvalidArgumentException(\sprintf(
                    'IPDO: Passing parameters without a conversion specifier in a request',
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
        $values = &$this->values;

        $holes = \array_count_values($holes);
        /** @var array<string, positive-int> $holes */

        // ---------------------------------------------
        // INFO берем только те ключи что есть в запросе
        // ---------------------------------------------

        $sizeBefore   = \count($values);
        $values = \array_intersect_key($values, $holes);
        $sizeAfter    = \count($values);

        if ($sizeBefore !== $sizeAfter) {
            throw new InvalidArgumentException(\sprintf(
                'IPDO: The number of parameters differs from the conversion specifier in the request.',
            ));
        }
        unset($sizeBefore);

        if (!$values) {
            throw new InvalidArgumentException(\sprintf(
                'IPDO: 2',
            ));
        }

        // ---------------------------------------------
        // INFO проверям что ключи из запроса есть в values
        // ---------------------------------------------

        if ($sizeAfter !== \count($holes)) {
            throw new InvalidArgumentException(\sprintf(
                'IPDO: 3',
            ));
        }

        // ---------------------------------------------
        // INFO переименовываем и заменяем дубли
        // ---------------------------------------------

        foreach ($holes as $name => $repeat) {
            $value = &$values[$name];
            $type = \gettype($value);
            // INFO переименовка дублей
            if ($repeat > 1) {
                for ($i = 0; $i < $repeat; $i++) {
                    // INFO валидируем обьекты
                    if ('object' === $type) {
                        /** @var object $value */
                        if (!($value instanceof ByteParamDTO)) {
                            throw new InvalidArgumentException(\sprintf(
                                'IPDO: 3.1',
                            ));
                        }
                        $newName = $this->getNewName();
                        $values[$newName] = clone $value;
                    }
                    // INFO тут же обрабатываем массив значений
                    elseif ('array' === $type) {
                        /** @var ParamIN[] $value */
                        $this->prepareSubValueArrayToInOperator($name, $value);
                        continue; // continue чтобы не выполнить нижний replaceFirst
                    } else {
                        $newName = $this->getNewName();
                        $values[$newName] = $value;
                    }

                    $this->query = Util::replaceFirst('{' . $name . '}', ' :' . $newName . ' ', $this->query);
                } // endfor
                unset($values[$name]);
            } // end repeat
            // INFO переименовка
            else {
                // INFO тут же обрабатываем массив значений
                if ('array' === $type) {
                    /** @var ParamIN[] $value */
                    $this->prepareSubValueArrayToInOperator($name, $value);
                } else {
                    $newName = $this->getNewName();
                    // INFO валидируем обьекты
                    if ('object' === $type) {
                        /** @var object $value */
                        if (!($value instanceof ByteParamDTO)) {
                            throw new InvalidArgumentException(\sprintf(
                                'IPDO: 3.2',
                            ));
                        }
                        $values[$newName] = clone $value;
                    } else {
                        $values[$newName] = $value;
                    }
                    $this->query = \str_replace('{' . $name . '}', ' :' . $newName . ' ', $this->query);
                }
                unset($values[$name]);
            }
        } // endforeach
        // unset($newName, $name, $repeat, $i);
    }

    // ---------------------------------------------
    // 
    // ---------------------------------------------

    /**
     * @param ParamIN[] $rawValue
     */
    protected function prepareSubValueArrayToInOperator(string $oldName, array $rawValue): void
    {
        if (Util::isMultidimensional($rawValue)) {
            throw new InvalidArgumentException(\sprintf(
                'IPDO: 4',
            ));
        }
        $newHoles = [];
        $values = &$this->values;
        foreach ($rawValue as $subValue) {
            // @phpstan-ignore-next-line дополнительная проверка. Тут null не должен быть, но user может по ошибке его сюда добавить.
            if (null === $subValue) {
                throw new InvalidArgumentException(\sprintf(
                    'IPDO: 5',
                ));
            }
            $newName = $this->getNewName();
            $newHoles[] = ':' . $newName;
            if ($subValue instanceof ByteParamDTO) {
                $values[$newName] = clone $subValue;
            } elseif (\is_scalar($subValue)) {
                $values[$newName] = $subValue;
            } else {
                throw new InvalidArgumentException(\sprintf(
                    'IPDO: 6',
                ));
            }
        } // endforeach
        $this->query = Util::replaceFirst('{' . $oldName . '}', ' ' . \implode(',', $newHoles) . ' ', $this->query);
    }

    protected function getNewName(): string
    {
        self::$num ??= \mt_rand(100, 999);
        self::$rnd ??= \bin2hex(\random_bytes(2));
        return self::$rnd . '_' . ++self::$num;
    }
}
