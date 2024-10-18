<?php

declare(strict_types=1);

namespace Inilim\IPDO;

use PDO;
use PDOStatement;
use Inilim\IPDO\IPDOResult;
use Inilim\IPDO\DTO\ByteParamDTO;
use Inilim\IPDO\DTO\QueryParamDTO;
use Inilim\IPDO\Exception\IPDOException;
use Inilim\IPDO\Exception\FailedExecuteException;

abstract class IPDO
{
    const FETCH_ALL         = 2;
    const FETCH_ONCE        = 1;
    const FETCH_IPDO_RESULT = 0;
    const LEN_SQL           = 500;

    protected string $host;
    protected string $nameDB;
    protected string $login;
    protected string $password;
    /**
     * @var array<int|string,mixed>
     */
    protected array $options;
    /**
     * Соединение с БД PDO
     */
    protected ?PDO $connect = null;
    /**
     * статус последнего запроса
     */
    protected bool $lastStatus = false;
    /**
     * количество соединений
     */
    protected int $countConnect = 0;
    /**
     * количество задейственных строк поледнего запроса.
     */
    protected int $countTouch   = 0;
    protected int $lastInsertID = -1;

    /**
     * выполнить запрос
     * @param int|array<string,mixed> $values
     * @param int $fetch 0 вернуть IPDOResult, 1 вытащить один результат, 2 вытащить все.
     * @return IPDOResult|list<array<string,array<string,string|null|int|float>>>|array<string,string|null|int|float>|array{}
     */
    function exec(
        string $query,
        $values    = [],
        int $fetch = self::FETCH_IPDO_RESULT
    ) {
        if (\is_int($values)) {
            $fetch  = $values;
            $values = [];
        }
        return $this->run(new QueryParamDTO($query, $values), $fetch);
    }

    /**
     * получить статус последнего запроса. В случаи отсутствия запроса выдаст false
     */
    function status(): bool
    {
        if ($this->connect === null) return false;
        return $this->lastStatus;
    }

    /**
     * закрыть соединение с базой
     */
    function close(): void
    {
        $this->connect = null;
    }

    /**
     * возвращает количество задейственных строк последнего запроса. Запросы типа SELECT тоже считаются!
     */
    function involved(): int
    {
        if ($this->connect === null) return -1;
        return $this->countTouch;
    }

    /**
     * получить автоинкремент. в противном случаи вернет -1
     * @deprecated use getLastInsertID()
     */
    function getLastInsert(): int
    {
        return $this->getLastInsertID();
    }

    /**
     * получить автоинкремент. в противном случаи вернет -1
     */
    function getLastInsertID(): int
    {
        if ($this->connect === null) return -1;
        return $this->lastInsertID;
    }

    /**
     * @throws \PDOException
     */
    function connect(): void
    {
        if ($this->connect === null) $this->connectDB();
    }

    function hasConnect(): bool
    {
        return $this->connect !== null;
    }

    /**
     * активируем транзакцию, если мы повторно активируем транзакцию выдаст false
     */
    function begin(): bool
    {
        if ($this->connect === null) return false;
        if ($this->connect->inTransaction()) {
            return false;
        }
        $this->connect->beginTransaction();
        return true;
    }

    function rollBack(): void
    {
        if ($this->connect === null) return;
        if ($this->inTransaction()) {
            $this->connect->rollBack();
        }
    }

    function commit(): bool
    {
        if ($this->connect === null) return false;
        if ($this->inTransaction()) {
            return $this->connect->commit();
        }
        return false;
    }

    function inTransaction(): bool
    {
        if ($this->connect === null) return false;
        return $this->connect->inTransaction();
    }

    // ---------------------------------------------
    // ---------------------------------------------
    // ---------------------------------------------
    // protected
    // ---------------------------------------------
    // ---------------------------------------------
    // ---------------------------------------------

    /**
     * @return IPDOResult|list<array<string,array<string,string|null|int|float>>>|array<string,string|null|int|float>|array{}
     */
    protected function run(QueryParamDTO $queryParam, int $fetch)
    {
        $this->countTouch   = 0;
        $this->lastInsertID = -1;
        return $this->fetchResult(
            $this->tryMainProccess($queryParam),
            $fetch
        );
    }

    /**
     * TODO fetch и fetchAll могут выбрасить исключение нужно это отловить
     * @return IPDOResult|list<array<string,array<string,string|null|int|float>>>|array<string,string|null|int|float>|array{}
     */
    protected function fetchResult(IPDOResult $result, int $fetch)
    {
        if ($fetch === self::FETCH_ONCE) {
            $list = $result->getStatement()->fetch(PDO::FETCH_ASSOC);
            return !\is_array($list) ? [] : $list;
        }
        if ($fetch === self::FETCH_ALL) {
            $list = $result->getStatement()->fetchAll(PDO::FETCH_ASSOC);
            return !\is_array($list) ? [] : $list;
        }

        return $result;
    }

    protected function tryMainProccess(QueryParamDTO $queryParam): IPDOResult
    {
        try {
            $this->lastStatus = true;
            return $this->mainProccess($queryParam);
        } catch (\Throwable $e) {
            $this->lastStatus = false;
            $ee = new FailedExecuteException($e->getMessage());
            $queryParam->query = $this->shortQuery($queryParam->query);

            if ($e instanceof IPDOException) {
                $ee->setError([
                    'query_param' => (array)$queryParam,
                    'error'       => $e->getError(),
                ]);
            } else {
                $ee->setError([
                    'query_param'      => (array)$queryParam,
                    'exception_object' => $e,
                ]);
            }

            throw $ee;
        }
    }

    /**
     * @throws IPDOException
     */
    protected function mainProccess(QueryParamDTO $queryParam): IPDOResult
    {
        $this->connectDB();

        if ($this->connect === null) {
            throw new IPDOException('IPDO::connectDB() property "connect" is null');
        }

        // IN OR NOT IN (:item,:item,:item)
        $this->arrayToIN($queryParam);

        // подготовка запроса
        $stm = $this->connect->prepare($queryParam->query);

        if ($stm === false) {
            $e = new IPDOException('PDO::prepare return false');
            $e->setError([
                $e->getMessage(),
                'PDO' => [
                    'error_info' => $this->connect->errorInfo(),
                ],
            ]);
            throw $e;
        }

        // Устанавливаем параметры к запросу
        $this->setBindParams($stm, $queryParam);

        // выполнить запрос
        if (!$stm->execute()) {
            $e = new IPDOException('PDOStatement::execute return false');
            $e->setError([
                $e->getMessage(),
                'PDOStatement' => [
                    'error_info'        => $stm->errorInfo(),
                    'debug_dump_params' => $this->getDebugDumpParams($stm),
                ]
            ]);
            throw $e;
        }

        return $this->defineResult($stm);
    }

    /**
     * @throws \PDOException
     */
    abstract protected function connectDB(): void;

    protected function getDebugDumpParams(PDOStatement $stm): string
    {
        \ob_start();
        $stm->debugDumpParams();
        return \strval(\ob_get_clean());
    }

    /**
     * @throws IPDOException
     */
    protected function arrayToIN(QueryParamDTO $queryParam): void
    {
        $mark = 'in_item_';
        $num = \mt_rand(1000, 9999);
        $queryBefore = $queryParam->query;
        foreach ($queryParam->values as $nameField => $value) {
            if (!\is_array($value)) continue;

            if ($this->isMultidimensional($value)) {
                $e = new IPDOException(\sprintf(
                    'value by name "%s" multidimensional array',
                    $nameField
                ));
                $e->setError([
                    $e->getMessage(),
                    '$nameField' => $nameField,
                    '$value'     => $value,
                ]);
                throw $e;
            }

            // создаем новые ключи
            $markKeys = \array_map(static function ($inItem) use (&$queryParam, $mark, &$num) {
                $newKey = $mark . $num;
                $queryParam->values[$newKey] = $inItem;
                $num++;
                return ':' . $newKey;
            }, $value);

            $queryParam->query = \preg_replace(
                '#\([\s\t]*\:' . \preg_quote($nameField) . '[\s\t]*\)#',
                '(' . \implode(',', $markKeys) . ')',
                $queryParam->query
            );

            if ($queryParam->query === null) {
                $queryParam->query = $queryBefore;
                $e = new IPDOException(\sprintf(
                    '%s: preg_replace return null',
                    __FUNCTION__,
                ));
                $e->setError([
                    '$nameField' => $nameField,
                    '$markKeys'  => $markKeys,
                ]);
                throw $e;
            }

            $markKeys = [];
            unset($queryParam->values[$nameField]);
        }
    }

    /**
     * @param PDOStatement $stm
     */
    protected function setBindParams(PDOStatement $stm, QueryParamDTO $queryParam): void
    {
        //$v = [];# массив для отладки
        // &$val требование от bindParam https://www.php.net/manual/ru/pdostatement.bindparam.php#98145
        foreach ($queryParam->values as $key => &$val) {
            $mask = ':' . $key;
            if ($this->isIntPHP($val)) {
                // @phpstan-ignore-next-line
                $val = \intval($val);
                $stm->bindParam($mask, $val, PDO::PARAM_INT);
            } elseif (\is_bool($val)) {
                $stm->bindParam($mask, $val, PDO::PARAM_BOOL);
            } elseif ($val === null) {
                $stm->bindParam($mask, $val, PDO::PARAM_NULL);
            } elseif (\is_object($val)) {
                if ($val instanceof ByteParamDTO) {
                    $val = $val->getValue();
                    $stm->bindParam($mask, $val, PDO::PARAM_LOB);
                }
            } else {
                $val = \strval($val);
                $stm->bindParam($mask, $val, PDO::PARAM_STR);
            }
        }
    }

    protected function defineResult(PDOStatement $stm): IPDOResult
    {
        return new IPDOResult(
            $stm,
            $this->countTouch   = $stm->rowCount(),
            $this->lastInsertID = $this->defineLastInsertID()
        );
    }

    protected function defineLastInsertID(): int
    {
        if ($this->connect === null) return -1;
        $id = $this->connect->lastInsertId();
        if ($this->isNumeric($id)) return \intval($id);
        // lastInsertId может вернуть строку, представляющую последнее значение
        return -1;
    }

    /**
     * форматируем запрос для логов
     */
    protected function shortQuery(string $query): string
    {
        $query = \str_replace(["\n", "\r", "\r\n", "\t"], ' ', $query);
        $query = \preg_replace('#\s{2,}#', ' ', $query) ?? '';
        if (\strlen($query) > self::LEN_SQL) {
            return \substr($query, 0, self::LEN_SQL) . '...';
        }
        return $query;
    }

    /**
     * TODO данный метод взят из библиотеки inilim/integer
     * проверка int для php, 32bit или 64bit
     * может ли значение стать integer без изменений
     * @param mixed $v
     */
    protected function isIntPHP($v): bool
    {
        if ($this->isNumeric($v)) {
            /** @var string $v */
            if (\strval(\intval($v)) === \strval($v)) return true;
            return false;
        }
        return false;
    }

    /**
     * TODO данный метод взят из библиотеки inilim/integer
     * @param mixed $v
     */
    protected function isNumeric($v): bool
    {
        if (!\is_scalar($v) || \is_bool($v)) return false;
        // here string|int|float
        if (\preg_match('#^\-?[1-9][0-9]{0,}$|^0$#', \strval($v))) return true;
        return false;
    }

    /**
     * TODO данный метод взят из библиотеки inilim/array
     * проверка на многомерный массив
     * true - многомерный
     * false - одномерный
     * @param mixed[] $arr
     */
    protected function isMultidimensional(array $arr): bool
    {
        return (\sizeof($arr) - \sizeof($arr, \COUNT_RECURSIVE)) !== 0;
    }
}
