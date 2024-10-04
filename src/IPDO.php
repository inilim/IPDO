<?php

namespace Inilim\IPDO;

use PDO;
use PDOStatement;
use Inilim\Array\Array_;
use Inilim\IPDO\ByteParamDTO;
use Inilim\Integer\Integer;
use Inilim\IPDO\IPDOResult;
use Inilim\IPDO\Exception\IPDOException;
use Inilim\IPDO\Exception\FailedExecuteException;

abstract class IPDO
{
   const FETCH_ALL         = 2;
   const FETCH_ONCE        = 1;
   const FETCH_IPDO_RESULT = 0;
   protected const LEN_SQL = 500;

   protected string $host;
   protected string $name_db;
   protected string $login;
   protected string $password;
   protected array $options;
   /**
    * Соединение с БД PDO
    */
   protected ?PDO $connect = null;
   protected Integer $integer;
   protected Array_ $array;
   /**
    * статус последнего запроса
    */
   protected bool $last_status = false;
   /**
    * количество соединений
    */
   protected int $count_connect = 0;
   /**
    * количество задейственных строк поледнего запроса.
    */
   protected int $count_touch    = 0;
   protected int $last_insert_id = -1;

   /**
    * выполнить запрос
    * @param int|array<string,mixed> $values
    * @param int $fetch 0 вернуть IPDOResult, 1 вытащить один результат, 2 вытащить все.
    * @return IPDOResult|list<array<string,array<string,string|null|int|float>>>|array<string,string|null|int|float>|array{}
    */
   function exec(
      string $sql_query,
      array|int $values = [],
      int $fetch        = self::FETCH_IPDO_RESULT
   ): array|IPDOResult {
      if (\is_int($values)) {
         $fetch  = $values;
         $values = [];
      }
      return $this->run($sql_query, $fetch, $values);
   }

   /**
    * получить статус последнего запроса. В случаи отсутствия запроса выдаст false
    */
   function status(): bool
   {
      if (!$this->hasConnect()) return false;
      return $this->last_status;
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
      if (!$this->hasConnect()) return -1;
      return $this->count_touch;
   }

   /**
    * получить автоинкремент. в противном случаи вернет -1
    */
   function getLastInsert(): int
   {
      if (!$this->hasConnect()) return -1;
      return $this->last_insert_id;
   }

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
      if (!$this->hasConnect()) return false;
      if ($this->connect->inTransaction()) {
         return false;
      }
      $this->connect->beginTransaction();
      return true;
   }

   function rollBack(): void
   {
      if ($this->inTransaction()) {
         $this->connect->rollBack();
      }
   }

   function commit(): bool
   {
      if ($this->inTransaction()) {
         return $this->connect->commit();
      }
      return false;
   }

   function inTransaction(): bool
   {
      if (!$this->hasConnect()) return false;
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
    * @param array<string,mixed> $values
    * @return IPDOResult|list<array<string,array<string,string|null|int|float>>>|array<string,string|null|int|float>|array{}
    */
   protected function run(
      string $sql,
      int $fetch,
      array $values = [],
   ): array|IPDOResult {
      $this->count_touch    = 0;
      $this->last_insert_id = -1;
      $result = $this->tryMainProccess($sql, $values);
      return $this->fetchResult($result, $fetch);
   }

   /**
    * TODO fetch и fetchAll могут выбрасить исключение нужно это отловить
    * @return IPDOResult|list<array<string,array<string,string|null|int|float>>>|array<string,string|null|int|float>|array{}
    */
   protected function fetchResult(IPDOResult $result, int $fetch): array|IPDOResult
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

   /**
    * @param array<string,mixed> $values
    */
   protected function tryMainProccess(string &$sql, array $values = []): IPDOResult
   {
      try {
         $this->last_status = true;
         return $this->mainProccess($sql, $values);
      } catch (\Throwable $e) {
         $this->last_status = false;
         throw new FailedExecuteException(
            query: $this->shortQuery($sql),
            e: $e,
            values: $values
         );
      }
   }

   /**
    * @param array<string,mixed> $values
    * @throws IPDOException
    */
   protected function mainProccess(string &$sql, array $values = []): IPDOResult
   {
      $this->connectDB();

      if ($this->connect === null) {
         throw new IPDOException('IPDO::connectDB() property "connect" is null');
      }

      // IN OR NOT IN (:item,:item,:item)
      $sql = $this->arrayToIN($values, $sql);

      $this->removeUnwantedKeys($values, $sql);

      // подготовка запроса
      $stm = $this->connect->prepare($sql);

      if (\is_bool($stm)) {
         $e = new IPDOException('PDO::prepare return false');
         $e->setError([
            $e->getMessage(),
            $this->connect->errorInfo(),
         ]);
         throw $e;
      }

      // Устанавливаем параметры к запросу
      $this->setBindParams($stm, $values);

      // выполнить запрос
      if (!$stm->execute()) {
         $e = new IPDOException('PDOStatement::execute return false');
         $e->setError([
            $e->getMessage(),
            $stm->errorInfo(),
         ]);
         throw $e;
      }

      return $this->defineResult($stm);
   }

   abstract protected function connectDB(): void;

   /**
    * удаляем ненужные ключи из массива $values
    * @param array<string,string|null|int|float|bool|ByteParamDTO> $values
    * @return string[]
    */
   protected function removeUnwantedKeys(array &$values, string $sql): array
   {
      if (!\str_contains($sql, ':')) return [];
      $masks = [];
      \preg_match_all('#\:[a-z\_A-Z0-9]+#', $sql, $masks);
      $masks = $masks[0] ?? [];
      if (!$masks) return [];
      $masks      = \array_map(static fn($m) => \ltrim($m, ':'), $masks);
      $masks_keys = \array_flip($masks);
      $values     = \array_intersect_key($values, $masks_keys);
      return $masks;
   }

   /**
    * @param array<string,mixed> $values
    * @throws IPDOException
    */
   protected function arrayToIN(array &$values, string &$sql): string
   {
      $mark = 'in_item_';
      $num = \mt_rand(1000, 9999);
      foreach ($values as $key_val => $val) {
         if (!\is_array($val)) continue;

         if ($this->array->isMultidimensional($val)) {
            $e = new IPDOException($key_val . ': многомерный массив.');
            $e->setError([
               $e->getMessage(),
               $val,
            ]);
            throw $e;
         }

         $mark_keys = \array_map(static function ($in_item) use (&$values, $mark, &$num) {
            $new_key = $mark . $num;
            $values[$new_key] = $in_item;
            $num++;
            return ':' . $new_key;
         }, $val);

         // $sql = str_replace(':' . $key_val, implode(',', $mark_keys), $sql);
         $sql = \preg_replace(
            '#\([\s\t]*\:' . \preg_quote($key_val) . '[\s\t]*\)#',
            '(' . \implode(',', $mark_keys) . ')',
            $sql
         );

         $mark_keys = [];
         unset($values[$key_val]);
      }

      return $sql;
   }

   /**
    * @param PDOStatement $stm
    * @param array<string,string|null|int|float|bool|ByteParamDTO> $values
    */
   protected function setBindParams(PDOStatement $stm, array &$values): void
   {
      //$v = [];# массив для отладки
      // &$val требование от bindParam https://www.php.net/manual/ru/pdostatement.bindparam.php#98145
      foreach ($values as $key => &$val) {
         $mask = ':' . $key;
         if ($this->integer->isIntPHP($val)) {
            $val = \intval($val);
            $stm->bindParam($mask, $val, PDO::PARAM_INT);
         } elseif (\is_bool($val)) {
            $stm->bindParam($mask, $val, PDO::PARAM_BOOL);
         } elseif ($val === null) {
            $stm->bindParam($mask, $val, PDO::PARAM_NULL);
         } elseif (\is_object($val)) {
            if ($val instanceof ByteParamDTO) {
               $stm->bindParam($mask, $val->value, PDO::PARAM_LOB);
            }
         } else {
            $val = \strval($val);
            $stm->bindParam($mask, $val, PDO::PARAM_STR);
         }
      }
   }

   protected function defineResult(PDOStatement $stm): IPDOResult
   {
      $this->count_touch    = $stm->rowCount();
      $this->last_insert_id = $this->getLastInsertID();

      return new IPDOResult(
         $stm,
         $this->count_touch,
         $this->last_insert_id
      );
   }

   protected function getLastInsertID(): int
   {
      $id = $this->connect->lastInsertId();
      if ($this->integer->isNumeric($id)) return \intval($id);
      // lastInsertId может вернуть строку, представляющую последнее значение
      return -1;
   }

   /**
    * форматируем запрос для логов
    */
   protected function shortQuery(string &$sql): string
   {
      $sql = \str_replace(["\n", "\r", "\r\n", "\t"], ' ', $sql);
      $sql = \preg_replace('#\s{2,}#', ' ', $sql) ?? '';
      if (\strlen($sql) > self::LEN_SQL) return \substr($sql, 0, self::LEN_SQL) . '...';
      return $sql;
   }
}
