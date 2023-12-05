<?php

namespace Inilim\IPDO;

use Inilim\IPDO\Exception\IPDOException;
use Inilim\IPDO\Exception\FailedExecuteException;
use Inilim\IPDO\IPDOResult;
use PDOStatement;
use PDO;

use function \str_contains;
use function replaceDoubleSpace;
use function integer;
use function isInt;
use function isMultidimensional;

abstract class IPDO
{
   const FETCH_ALL            = 2;
   const FETCH_ONCE           = 1;
   const FETCH_IPDO_RESULT    = 0;
   protected const LEN_SQL    = 500;

   protected string $host;
   protected string $name_db;
   protected string $login;
   protected string $password;
   /**
    * Соединение с БД PDO
    */
   protected ?PDO $connect = null;
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
    * @return @return IPDOResult|list<array<string,array<string,string|null|int|float>>>|array<string,string|null|int|float>|array{}
    */
   public function exec(
      string $sql_query,
      array|int $values = [],
      int $fetch        = self::FETCH_IPDO_RESULT
   ): array|IPDOResult {
      if (is_int($values)) {
         $fetch  = $values;
         $values = [];
      }
      return $this->run($sql_query, $values, $fetch);
   }

   /**
    * получить статус последнего запроса. В случаи отсутствия запроса выдаст false
    */
   public function status(): bool
   {
      if (!$this->hasConnect()) return false;
      return $this->last_status;
   }

   /**
    * закрыть соединение с базой
    */
   public function close(): void
   {
      $this->connect = null;
   }

   /**
    * возвращает количество задейственных строк последнего запроса. Запросы типа SELECT тоже считаются!
    */
   public function involved(): int
   {
      if (!$this->hasConnect()) return -1;
      return $this->count_touch;
   }

   /**
    * получить автоинкремент. в противном случаи вернет -1
    */
   public function getLastInsert(): int
   {
      if (!$this->hasConnect()) return -1;
      return $this->last_insert_id;
   }

   public function connect(): void
   {
      if (is_null($this->connect)) $this->connectDB();
   }

   public function hasConnect(): bool
   {
      return !is_null($this->connect);
   }

   /**
    * активируем транзакцию, если мы повторно активируем транзакцию выдаст false
    */
   public function begin(): bool
   {
      if (!$this->hasConnect()) return false;
      if ($this->connect->inTransaction()) {
         return false;
      }
      $this->connect->beginTransaction();
      return true;
   }

   public function rollBack(): void
   {
      if ($this->inTransaction()) {
         $this->connect->rollBack();
      }
   }

   public function commit(): bool
   {
      if ($this->inTransaction()) {
         return $this->connect->commit();
      }
      return false;
   }

   public function inTransaction(): bool
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
      array $values = [],
      int $fetch    = self::FETCH_IPDO_RESULT
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
         return !is_array($list) ? [] : $list;
      }
      if ($fetch === self::FETCH_ALL) {
         $list = $result->getStatement()->fetchAll(PDO::FETCH_ASSOC);
         return !is_array($list) ? [] : $list;
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

      if (is_null($this->connect)) throw new IPDOException('IPDO::connectDB property "connect" is null');

      // IN OR NOT IN (:item,:item,:item)
      $sql = $this->convertList($values, $sql);

      $this->removeUnwantedKeys($values, $sql);

      // подготовка запроса
      $stm = $this->connect->prepare($sql);

      if (is_bool($stm)) throw new IPDOException([
         'PDO::prepare return false',
         $this->connect->errorInfo(),
      ]);

      // Устанавливаем параметры к запросу
      $this->setBindParams($stm, $values);

      // выполнить запрос
      if (!$stm->execute()) throw new IPDOException([
         'PDOStatement::execute return false',
         $stm->errorInfo(),
      ]);

      return $this->defineResult($stm);
   }

   protected function connectDB(): void
   {
   }

   /**
    * удаляем ненужные ключи из массива $values
    * @param array<string,string|null|int|float> $values
    * @return string[]
    */
   protected function removeUnwantedKeys(array &$values, string $sql): array
   {
      if (!str_contains($sql, ':')) return [];
      $masks = [];
      preg_match_all('#\:[a-z\_A-Z0-9]+#', $sql, $masks);
      $masks = $masks[0] ?? [];
      if (!sizeof($masks)) return $masks;
      $masks      = array_map(fn ($m) => trim($m, ':'), $masks);
      $masks_keys = array_flip($masks);
      $values     = array_intersect_key($values, $masks_keys);
      return $masks;
   }

   /**
    * @param array<string,mixed> $values
    * @throws IPDOException
    */
   protected function convertList(array &$values, string &$sql): string
   {
      $mark = 'in_item_';
      $num = mt_rand(1000, 9999);
      foreach ($values as $key_val => $val) {
         if (!is_array($val)) continue;
         if (isMultidimensional($val)) throw new IPDOException([
            $key_val . ': многомерный массив.',
            $val,
         ]);

         $mark_keys = array_map(function ($val_item) use (&$values, $mark, &$num) {
            $new_key = $mark . $num;
            $values[$new_key] = $val_item;
            $num++;
            return ':' . $new_key;
         }, $val);

         $sql = str_replace(':' . $key_val, implode(',', $mark_keys), $sql);
         unset($values[$key_val]);
      }

      return $sql;
   }

   /**
    * @param PDOStatement $stm
    * @param array<string,string|null|int|float> $values
    */
   protected function setBindParams(PDOStatement $stm, array &$values): void
   {
      //$v = [];# массив для отладки
      // &$val требование от bindParam https://www.php.net/manual/ru/pdostatement.bindparam.php#98145
      foreach ($values as $key => &$val) {
         $mask = ':' . $key;
         if (integer()->isIntPHP($val)) {
            $val = intval($val);
            $stm->bindParam($mask, $val, PDO::PARAM_INT);
         } elseif (is_null($val)) {
            $stm->bindParam($mask, $val, PDO::PARAM_NULL);
         } else {
            $val = strval($val);
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
      if (isInt($id)) return intval($id);
      // lastInsertId может вернуть строку, представляющую последнее значение
      return -1;
   }

   /**
    * форматируем запрос для логов
    */
   protected function shortQuery(string &$sql): string
   {
      $sql = str_replace(["\n", "\r", "\r\n", "\t"], ' ', $sql);
      $sql = replaceDoubleSpace($sql);
      if (strlen($sql) > self::LEN_SQL) return substr($sql, 0, self::LEN_SQL) . '...';
      return $sql;
   }
}
