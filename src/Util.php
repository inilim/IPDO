<?php

declare(strict_types=1);

namespace Inilim\IPDO;

final class Util
{
    /**
     * форматируем запрос для логов
     */
    static function strLimit(string $string, int $limit): string
    {
        $string = \str_replace(["\n", "\r", "\r\n", "\t"], ' ', $string);
        $string = \preg_replace('#\s{2,}#', ' ', $string) ?? '';
        if (\strlen($string) > $limit) {
            return \substr($string, 0, $limit) . '...';
        }
        return $string;
    }

    /**
     * TODO данный метод взят из библиотеки inilim/tools
     * @param mixed $v
     */
    static function isIntPHP($v): bool
    {
        if (self::isNumeric($v)) {
            /** @var string $v */
            if (\strval(\intval($v)) === \strval($v)) {
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * TODO данный метод взят из библиотеки inilim/tools
     * @param mixed $v
     */
    static function isNumeric($v): bool
    {
        if (!\is_scalar($v) || \is_bool($v)) {
            return false;
        }
        // here string|int|float
        if (\preg_match('#^\-?[1-9][0-9]{0,}$|^0$#', \strval($v))) {
            return true;
        }
        return false;
    }

    /**
     * @param mixed[] $array
     */
    static function isMultidimensional(array $array): bool
    {
        foreach ($array as $item) {
            if (\is_array($item)) {
                return true;
            }
        }
        return false;
    }

    static function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }
        $position = \strpos($subject, $search);
        if ($position !== false) {
            return \substr_replace($subject, $replace, $position, \strlen($search));
        }
        return $subject;
    }
}
