<?php

declare(strict_types=1);

namespace Inilim\IPDO;

use PDO;
use PDOStatement;
use Inilim\IPDO\Util;
use Inilim\IPDO\IPDOResult;
use Inilim\IPDO\DTO\QueryParamDTO;
use Inilim\IPDO\Exception\IPDOException;

/**
 * @psalm-import-type Param from QueryParamDTO
 * @psalm-import-type ParamIN from QueryParamDTO
 * 
 * @psalm-type FETCH_ONCE     = array<string,string|null|float|int>
 * @psalm-type FETCH_ALL      = (array<string,string|null|float|int>)[]
 * @psalm-type FETCH_ONCE_NUM = (string|null|float|int)[]
 * @psalm-type FETCH_ALL_NUM  = ((string|null|float|int)[])[]
 */
abstract class IPDO
{
    const FETCH_ALL       = 2,
        FETCH_ONCE        = 1,
        FETCH_IPDO_RESULT = 0,
        FETCH_ALL_NUM     = 3,
        FETCH_ONCE_NUM    = 4;

    protected const LEN_SQL = 500;

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
    protected ?string $rawLastInsertID = null;

    // const FETCH_ALL       = 2,
    //     FETCH_ONCE        = 1,
    //     FETCH_IPDO_RESULT = 0,
    //     FETCH_ALL_NUM     = 3,
    //     FETCH_ONCE_NUM    = 4,

    /**
     * @param int|Param|ParamIN[] $values
     * @param self::FETCH_* $fetch default self::FETCH_IPDO_RESULT
     * 
     * @return ($fetch is 1 ? FETCH_ONCE : ($fetch is 2 ? FETCH_ALL : ($fetch is 4 ? FETCH_ONCE_NUM : ($fetch is 3 ? FETCH_ALL_NUM : IPDOResult))))
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

        $this->lastStatus      = true;
        $this->countTouch      = 0;
        $this->lastInsertID    = -1;
        $this->rawLastInsertID = null;
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
        if ($this->connect === null) {
            return false;
        }
        return $this->lastStatus;
    }

    /**
     * @return array<int|string,mixed>
     */
    function getOptions(): array
    {
        return $this->options;
    }

    function getNameDb(): string
    {
        return $this->nameDB;
    }

    function getPDO(): ?PDO
    {
        return $this->connect;
    }

    function setPDO(PDO $pdo): self
    {
        $this->connect = $pdo;
        return $this;
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
        if ($this->connect === null) {
            return -1;
        }
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
        if ($this->connect === null) {
            return -1;
        }
        return $this->lastInsertID;
    }

    function getRawLastInsertID(): ?string
    {
        return $this->rawLastInsertID;
    }

    /**
     * init connection
     * @return self
     * @throws \PDOException
     */
    function connect()
    {
        if ($this->connect === null) {
            $this->connectDB();
        }

        return $this;
    }

    /**
     * Whether an actual connection to the database is established.
     *
     * @phpstan-assert-if-true !null $this->connect
     */
    function isConnected(): bool
    {
        return $this->connect !== null;
    }

    /**
     * @deprecated use isConnected
     * @phpstan-assert-if-true !null $this->connect
     */
    function hasConnect(): bool
    {
        return $this->connect !== null;
    }

    /**
     * активируем транзакцию, если мы повторно активируем транзакцию выдаст false
     */
    function begin(): bool
    {
        if ($this->connect === null) {
            return false;
        }
        if ($this->connect->inTransaction()) {
            return false;
        }
        $this->connect->beginTransaction();
        return true;
    }

    function rollBack(): void
    {
        if ($this->connect === null) {
            return;
        }
        if ($this->inTransaction()) {
            $this->connect->rollBack();
        }
    }

    function commit(): bool
    {
        if ($this->connect === null) {
            return false;
        }
        if ($this->inTransaction()) {
            return $this->connect->commit();
        }
        return false;
    }

    /**
     * @param \Closure(self) $callable
     * @return self
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

        return $this;
    }

    function inTransaction(): bool
    {
        if ($this->connect === null) {
            return false;
        }
        return $this->connect->inTransaction();
    }

    // ---------------------------------------------
    // 
    // ---------------------------------------------

    /**
     * @param self::FETCH_* $fetch
     * @return IPDOResult|FETCH_ALL|FETCH_ALL_NUM|FETCH_ONCE|FETCH_ONCE_NUM
     */
    protected function fetchResult(IPDOResult $result, int $fetch)
    {
        if (!\in_array($fetch, [self::FETCH_ONCE_NUM, self::FETCH_ONCE, self::FETCH_ALL, self::FETCH_ALL_NUM], true)) {
            return $result;
        }

        $stm = $result->getStatement();
        try {
            if ($fetch === self::FETCH_ONCE_NUM) {
                $list = $stm->fetch(PDO::FETCH_NUM);
            }
            if ($fetch === self::FETCH_ONCE) {
                $list = $stm->fetch(PDO::FETCH_ASSOC);
            }
            if ($fetch === self::FETCH_ALL) {
                $list = $stm->fetchAll(PDO::FETCH_ASSOC);
            }
            if ($fetch === self::FETCH_ALL_NUM) {
                $list = $stm->fetchAll(PDO::FETCH_NUM);
            }
        } catch (\PDOException $e) {
            throw new IPDOException([
                'message'          => $e->getMessage(),
                'code'             => $e->getCode(),
                'exception_object' => $e,
            ]);
        }

        return \is_array($list) ? $list : [];
    }

    protected function tryProcess(QueryParamDTO $queryParam): IPDOResult
    {
        try {
            return $this->process($queryParam);
        } catch (\Throwable $e) {
            $this->lastStatus  = false;
            $queryParam->query = Util::strLimit($queryParam->query, self::LEN_SQL);

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

        // 

        return new IPDOResult(
            $stm,
            $this->countTouch   = $stm->rowCount(),
            $this->lastInsertID = $this->defineLastInsertID(),
            $this->rawLastInsertID
        );
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
            if (Util::isIntPHP($val)) {
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

    protected function defineLastInsertID(): int
    {
        if ($this->connect === null) {
            return -1;
        }
        try {
            $this->rawLastInsertID = $id = $this->connect->lastInsertId();
        } catch (\Throwable $e) {
            return -1;
        }
        if (Util::isNumeric($id)) {
            return \intval($id);
        }
        // lastInsertId может вернуть строку, представляющую последнее значение
        return -1;
    }
}
