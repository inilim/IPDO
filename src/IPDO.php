<?php

declare(strict_types=1);

namespace Inilim\IPDO;

use PDO;
use PDOStatement;
use Inilim\IPDO\IPDOResult;
use Inilim\IPDO\DTO\ByteParamDTO;
use Inilim\IPDO\DTO\QueryParamDTO;
use Inilim\IPDO\Exception\IPDOException;

/**
 * @psalm-import-type Param from QueryParamDTO
 * @psalm-import-type ParamIN from QueryParamDTO
 */
abstract class IPDO
{
    const FETCH_ALL         = 2,
        FETCH_ONCE        = 1,
        FETCH_IPDO_RESULT = 0,
        LEN_SQL           = 500;

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
     * @param int|Param|ParamIN[] $values
     * @param self::FETCH_* $fetch 0 вернуть IPDOResult, 1 вытащить один результат, 2 вытащить все.
     * @return ($fetch is 0
     *      ? IPDOResult
     *      : (
     *              $fetch is 1
     *                  ? array<string,string|null|int|float>
     *                  : list<array<string,string|null|int|float>>
     *        )
     * )
     * 
     * @throws \InvalidArgumentException
     * @throws IPDOException
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

        $this->lastStatus   = true;
        $this->countTouch   = 0;
        $this->lastInsertID = -1;
        return $this->fetchResult(
            $this->tryProcess(new QueryParamDTO($query, $values)),
            $fetch
        );
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
     * @return void
     */
    function close()
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
     * @return void
     */
    function connect()
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

    /**
     * @return void
     */
    function rollBack()
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

    /**
     * @param \Closure(self) $callable
     * @return void
     */
    function transaction(\Closure $callable)
    {
        $this->connectDB();
        if (!$this->begin()) {
            throw new IPDOException([
                'message' => 'Begin failed',
            ]);
        }

        $callable->__invoke($this);

        if (!$this->commit()) {
            $this->rollBack();
            throw new IPDOException([
                'message' => 'Commit failed',
            ]);
        }
    }

    function inTransaction(): bool
    {
        if ($this->connect === null) return false;
        return $this->connect->inTransaction();
    }

    function setPDO(PDO $pdo): self
    {
        $this->connect = $pdo;
        return $this;
    }

    // ---------------------------------------------
    // ---------------------------------------------
    // ---------------------------------------------
    // protected
    // ---------------------------------------------
    // ---------------------------------------------
    // ---------------------------------------------

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

    protected function tryProcess(QueryParamDTO $queryParam): IPDOResult
    {
        try {
            return $this->process($queryParam);
        } catch (\Throwable $e) {
            $this->lastStatus  = false;
            $queryParam->query = $this->shortQuery($queryParam->query);

            $errorInfo = [
                'query_param' => (array)$queryParam,
            ];

            if ($e instanceof IPDOException) {
                throw new IPDOException($errorInfo + $e->getError());
            } else {
                throw new IPDOException($errorInfo + [
                    'message'          => $e->getMessage(),
                    'code'             => $e->getCode(),
                    'exception_object' => $e,
                ]);
            }
        }
    }

    /**
     * @throws IPDOException
     */
    protected function process(QueryParamDTO $queryParam): IPDOResult
    {
        $this->connectDB();

        if ($this->connect === null) {
            throw new IPDOException([
                'message' => 'IPDO::connectDB() property "connect" is null',
            ]);
        }

        // подготовка запроса
        $stm = $this->connect->prepare($queryParam->query);

        if ($stm === false) {
            throw new IPDOException([
                'message' => 'PDO::prepare return false',
                'PDO' => [
                    'error_info' => $this->connect->errorInfo(),
                ],
            ]);
        }

        // Устанавливаем параметры к запросу
        $this->setBindParams($stm, $queryParam);

        // выполнить запрос
        if (!$stm->execute()) {
            throw new IPDOException([
                'message' => 'PDOStatement::execute return false',
                'PDOStatement' => [
                    'error_info'        => $stm->errorInfo(),
                    'debug_dump_params' => $this->getDebugDumpParams($stm),
                ]
            ]);
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
     * @param PDOStatement $stm
     * @return void
     */
    protected function setBindParams(PDOStatement $stm, QueryParamDTO $queryParam)
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
                $val = $val->getValue();
                $stm->bindParam($mask, $val, PDO::PARAM_LOB);
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
}
