<?php

declare(strict_types=1);

namespace Inilim\IPDO;

use Inilim\IPDO\DTO\QueryParamDTO;
use Inilim\IPDO\Exception\IPDOException;
use Inilim\IPDO\IPDO;

/**
 * @deprecated Experimental class, not recommended for use in production
 * @psalm-import-type Param from QueryParamDTO
 * @psalm-import-type ParamIN from QueryParamDTO
 * Класс для выполнения батч-транзакций.
 */
class BatchIPDO
{
    protected IPDO $ipdo;
    protected int $batchSize;
    protected int $countInBatch = 0;
    protected bool $isTransactionOwner = false;
    protected bool $autoDestruct = true;

    /**
     * @param int $batchSize размер батча (количество изменяющих запросов в одной транзакции)
     * @throws \InvalidArgumentException если $batchSize <= 0
     */
    function __construct(IPDO $ipdo, int $batchSize = 100)
    {
        if ($batchSize <= 0) {
            throw new \InvalidArgumentException('Batch size must be greater than 0.');
        }
        $this->ipdo = $ipdo;
        $this->batchSize = $batchSize;
    }

    function getConnect(): IPDO
    {
        return $this->ipdo;
    }

    /**
     * Выполняет запрос, аналогично IPDO::exec, но с управлением транзакциями.
     * При достижении лимита текущая транзакция коммитится и начинается новая.
     *
     * @param string $query SQL-запрос
     * @param IPDO::FETCH_*|array<string,Param|ParamIN[]> $values параметры или режим fetch (совместимо с IPDO::exec)
     * @param IPDO::FETCH_* $fetch режим выборки
     * @return mixed результат, аналогичный IPDO::exec
     * @throws \Throwable если запрос не удался – транзакция откатывается, исключение пробрасывается
     */
    function exec(string $query, $values = [], int $fetch = IPDO::FETCH_IPDO_RESULT)
    {
        // Если внешняя транзакция уже активна, мы не можем управлять батчами.
        // В этом случае просто выполняем запрос без счётчика, но с предупреждением.
        // Можно выбросить исключение, но для гибкости разрешим выполнение без батчинга.
        if ($this->ipdo->inTransaction() && !$this->isTransactionOwner) {
            // Внешняя транзакция – просто выполняем запрос без учёта батча.
            return $this->ipdo->exec($query, $values, $fetch);
        }

        // Убедимся, что транзакция начата (если мы её владелец)
        if (!$this->isTransactionOwner && !$this->ipdo->inTransaction()) {
            if (!$this->ipdo->begin()) {
                throw new IPDOException([
                    'message' => 'Unable to start transaction.',
                ]);
            }
            $this->isTransactionOwner = true;
            $this->countInBatch = 0;
        }

        // Выполняем запрос
        try {
            $result = $this->ipdo->exec($query, $values, $fetch);
        } catch (\Throwable $e) {
            // При ошибке откатываем транзакцию, если мы её владелец
            if ($this->isTransactionOwner && $this->ipdo->inTransaction()) {
                $this->ipdo->rollBack();
            }
            $this->isTransactionOwner = false;
            $this->countInBatch = 0;
            throw $e;
        }

        $this->countInBatch++;
        // Если достигли лимита – коммитим и начинаем новую транзакцию
        if ($this->countInBatch >= $this->batchSize) {
            $this->commitAndBeginNew();
        }

        return $result;
    }

    /**
     * Принудительно завершает текущую транзакцию (коммит)
     * Если нет активной транзакции, ничего не делает.
     */
    function flush(): void
    {
        if ($this->isTransactionOwner && $this->ipdo->inTransaction()) {
            if (!$this->ipdo->commit()) {
                throw new IPDOException([
                    'message' => 'Failed to commit transaction during flush.',
                ]);
            }
            $this->isTransactionOwner = false;
            $this->countInBatch = 0;
            // Не начинаем новую транзакцию – владелец сбрасывается
        }
    }

    /**
     * Коммитит текущую транзакцию и сразу начинает новую.
     * Используется внутри при достижении лимита.
     */
    protected function commitAndBeginNew(): void
    {
        if ($this->isTransactionOwner && $this->ipdo->inTransaction()) {
            if (!$this->ipdo->commit()) {
                throw new IPDOException([
                    'message' => 'Failed to commit transaction during batch commit.',
                ]);
            }
            // Начинаем новую
            if (!$this->ipdo->begin()) {
                throw new IPDOException([
                    'message' => 'Unable to start new transaction after commit.',
                ]);
            }
            $this->countInBatch = 0;
        }
    }

    /**
     * Откатывает текущую транзакцию, если она принадлежит батчу.
     * После отката владение транзакцией сбрасывается.
     */
    function rollback(): void
    {
        if ($this->isTransactionOwner && $this->ipdo->inTransaction()) {
            $this->ipdo->rollBack();
            $this->isTransactionOwner = false;
            $this->countInBatch = 0;
        }
    }

    /**
     * Возвращает количество запросов в текущем батче.
     */
    function getCountInBatch(): int
    {
        return $this->countInBatch;
    }

    /**
     * Включает автоматический вызов flush() в деструкторе.
     */
    function enableAutoDestruct(): void
    {
        $this->autoDestruct = true;
    }

    /**
     * Отключает автоматический вызов flush() в деструкторе.
     */
    function disableAutoDestruct(): void
    {
        $this->autoDestruct = false;
    }

    /**
     * Деструктор: если autoDestruct включён и есть активная транзакция, выполняет flush().
     */
    function __destruct()
    {
        if ($this->autoDestruct && $this->isTransactionOwner && $this->ipdo->inTransaction()) {
            try {
                $this->flush();
            } catch (\Throwable $e) {
                // В деструкторе мы не можем пробросить исключение, поэтому просто заглушаем.
                // Можно залогировать, но здесь опускаем.
            }
        }
    }
}
