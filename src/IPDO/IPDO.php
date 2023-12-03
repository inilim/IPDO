<?php

namespace Inilim\IPDO;

use Inilim\IPDO\Exception\IPDOException;
use Inilim\IPDO\Exception\FailedExecuteException;
use Inilim\IPDO\IPDOResult;
use PDOStatement;
use PDO;

use function \str_contains;
use function replaceDoubleSpace;
use function isInt;
use function isMultidimensional;
use function CollectDataException;
use function writeLog;

class IPDO
{
   protected string $host     = 'localhost';
   protected string $name_db  = 'inilim';
   protected string $login    = 'root';
   protected string $password = '';
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
   protected const LEN_SQL = 500;

   const FETCH_ALL           = 2;
   const FETCH_ONCE          = 1;
   const FETCH_IPDO_RESULT   = 0;

   protected static ?self $obj = null;

   // ---------------------------------------------
   // ---------------------------------------------
   // ---------------------------------------------
   // public static
   // ---------------------------------------------
   // ---------------------------------------------
   // ---------------------------------------------

   /**
    * выполнить запрос
    * @param int|array<string,mixed> $values
    * @param int $fetch 0 вернуть IPDOResult, 1 вытащить один результат, 2 вытащить все.
    * @return @return IPDOResult|list<array<string,array<string,string|null|int|float>>>|array<string,string|null|int|float>|array{}
    */
   public static function exec(
      string $sql_query,
      array|int $values = [],
      int $fetch        = self::FETCH_IPDO_RESULT
   ): array|IPDOResult {
      if (!self::isInit()) self::init();
      if (is_int($values)) {
         $fetch  = $values;
         $values = [];
      }
      return self::$obj->run($sql_query, $values, $fetch);
   }

   /**
    * получить статус последнего запроса. В случаи отсутствия запроса выдаст false
    */
   public static function status(): bool
   {
      if (!self::isInit()) return false;
      return self::$obj->last_status;
   }

   /**
    * закрыть соединение с базой
    */
   public static function close(): void
   {
      if (!self::isInit()) return;
      self::$obj->closeConnect();
   }

   /**
    * возвращает количество задейственных строк последнего запроса. Запросы типа SELECT тоже считаются!
    */
   public static function involved(): int
   {
      if (!self::isInit()) return -1;
      return self::$obj->count_touch;
   }

   /**
    * получить автоинкремент. в противном случаи вернет -1
    */
   public static function getLastInsert(): int
   {
      if (!self::isInit()) return -1;
      return self::$obj->last_insert_id;
   }

   public static function init(): void
   {
      if (!is_null(self::$obj)) return;
      self::$obj = new self();
      self::$obj->connectDB();
   }

   public static function isInit(): bool
   {
      return !is_null(self::$obj);
   }

   /**
    * активируем транзакцию, если мы повторно активируем транзакцию будет вызван rollBack
    */
   public static function begin(): bool
   {
      if (!self::isInit()) return false;
      if (is_null(self::$obj->connect)) return false;
      if (self::$obj->connect->inTransaction()) {
         self::$obj->connect->rollBack();
         return false;
      }
      self::$obj->connect->beginTransaction();
      return true;
   }

   public static function rollBack(): void
   {
      if (self::inTransaction()) {
         self::$obj->connect->rollBack();
      }
   }

   public static function commit(): bool
   {
      if (self::inTransaction()) {
         return self::$obj->connect->commit();
      }
      return false;
   }

   public static function inTransaction(): bool
   {
      if (!self::isInit()) return false;
      if (is_null(self::$obj->connect)) return false;
      return self::$obj->connect->inTransaction();
   }

   // ---------------------------------------------
   // ---------------------------------------------
   // ---------------------------------------------
   // protected
   // ---------------------------------------------
   // ---------------------------------------------
   // ---------------------------------------------

   protected function closeConnect(): void
   {
      $this->connect = null;
   }

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

   /**
    * В момент создания PDO может выбросить исключение PDOException
    * @throws PDOException
    */
   protected function connectDB(): void
   {
      if (!is_null($this->connect)) return;

      $this->count_connect++;
      $this->connect = new PDO(
         'mysql:dbname=' . $this->name_db .
            ';host=' . $this->host,
         $this->login,
         $this->password,
         [
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
            // PDO::ATTR_EMULATE_PREPARES => false,
         ]
      );
      $this->connect->exec('SET NAMES utf8mb4');
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
