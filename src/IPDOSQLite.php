<?php

declare(strict_types=1);

namespace Inilim\IPDO;

use Inilim\IPDO\IPDO;
use Inilim\IPDO\Exception\IPDOException;

class IPDOSQLite extends IPDO
{
   /**
    * @param string $pathToFile "path/to/file" OR ":memory:"
    * @param array<int|string,mixed> $options
    */
   function __construct(string $pathToFile, array $options = [])
   {
      $this->nameDB  = $pathToFile;
      $this->options = $options;
   }

   // ATTACH START

   /**
    * @param string|IPDOSQLite $db
    *
    * @throws IPDOException
    * @throws \InvalidArgumentException
    */
   function attachDb($db, string $name): self
   {
      if (\in_array($name, ['', 'main', 'temp'], true)) {
         throw new \InvalidArgumentException('Database name cannot be empty, "main", or "temp".');
      }

      $this->exec('ATTACH DATABASE {db} AS {name}', [
         'name' => $name,
         'db' => $this->getPtfDb($db),
      ]);

      return $this;
   }

   /**
    * @throws IPDOException
    * @throws \InvalidArgumentException
    */
   function detachDbByName(string $name): self
   {
      if (\in_array($name, ['', 'main', 'temp'], true)) {
         throw new \InvalidArgumentException('Database name cannot be empty, "main", or "temp".');
      }
      $this->exec('DETACH DATABASE {name}', ['name' => $name]);
      return $this;
   }

   /**
    * @param string|IPDOSQLite $db
    * @throws IPDOException
    * @throws \InvalidArgumentException
    */
   function detachDbByDb($db): self
   {
      $name = $this->exec('SELECT [name] FROM pragma_database_list WHERE [name] != "main" AND [file] = {file}', [
         'file' => $this->getPtfDb($db),
      ], self::FETCH_ONCE_NUM);
      $name = $name[0] ?? null;

      if (\is_string($name)) {
         try {
            $this->exec('DETACH DATABASE {name}', ['name' => $name]);
         } catch (IPDOException $e) {
            // skip race
            $message = $e->getMessage();
            if (\strpos($message, 'no such database') === false) {
               throw $e;
            }
         }
      }

      return $this;
   }

   function hasAttachDbByName(string $name): bool
   {
      if (\in_array($name, ['', 'main', 'temp'], true)) {
         return false;
      }
      return $this->exists('SELECT * FROM pragma_database_list WHERE [name] = {name}', [
         'name' => $name,
      ]);
   }

   /**
    * @param string|IPDOSQLite $db
    */
   function hasAttachDbByDb($db): bool
   {
      return $this->exists('SELECT * FROM pragma_database_list WHERE [name] != "main" AND [file] = {file}', [
         'file' => $this->getPtfDb($db),
      ]);
   }

   /**
    * @return (array{name:string,file:string})[]
    */
   function databaseListAttach(): array
   {
      return $this->exec('SELECT [name], [file] FROM pragma_database_list WHERE [name] not in ("main","temp")', [], self::FETCH_ALL);
   }

   /**
    * @param string|IPDOSQLite $db
    * @throws \InvalidArgumentException
    */
   protected function getPtfDb($db): string
   {
      if ($db instanceof IPDOSQLite) {
         $db = $db->getMainFile();
         if ('' === $db) {
            throw new \InvalidArgumentException('The IPDOSQLite object does not have a main file path.');
         }
      } elseif (!\is_string($db)) {
         throw new \InvalidArgumentException('Database parameter must be a string or an instance of IPDOSQLite.');
      } else {
         // TODO URI format
         $real = (new \SplFileInfo($db))->getRealPath();
         if (false === $real) {
            throw new \InvalidArgumentException(\sprintf('File database not found "%s"', $db));
         }
         $db = $real;
      }

      // $db = \strtr($db, '\\', '/');

      return $db;
   }

   // ATTACH END

   /**
    * @return (array{cid:int,name:string,type:string,notnull:0|1,dflt_value:null|string|int|float,pk:0|1})[]
    */
   function tableInfo(string $tableName): array
   {
      return $this->exec('SELECT * FROM pragma_table_info({tbl})', ['tbl' => $tableName], self::FETCH_ALL);
   }

   /**
    * @return (array{seq:int,name:string,file:string})[]
    */
   function databaseList(): array
   {
      return $this->exec('SELECT * FROM pragma_database_list', [], self::FETCH_ALL);
   }

   /**
    * The absolute file path of the database file on disk. This will be an empty string if the database is in-memory or not associated with a physical file.
    */
   function getMainFile(): string
   {
      return \strval(
         $this->exec('SELECT [file] FROM pragma_database_list WHERE [name] = "main"', [], self::FETCH_ONCE_NUM)[0]
      );
   }

   /**
    * get version sqlite
    */
   function getVersion(): string
   {
      return \strval($this->exec('SELECT sqlite_version() AS "0"', [], self::FETCH_ONCE)[0]);
   }

   /**
    * @see https://www.php.net/manual/ru/pdo-sqlite.createfunction.php
    * @param callable(mixed $value,mixed ...$values):mixed $callback
    * @return static
    */
   function createFunction(string $function_name, callable $callback, int $num_args = -1, int $flags = 0)
   {
      if (null === $this->connect) {
         $this->connectDB();
      }
      $this->connect->sqliteCreateFunction($function_name, $callback, $num_args, $flags);
      return $this;
   }

   /**
    * @see https://www.php.net/manual/ru/pdo-sqlite.createaggregate.php
    * @param callable(mixed $context,int $rownumber,mixed $value,mixed ...$values):mixed $step
    * @param callable(mixed $context,int $rownumber):mixed $finalize
    * @return static
    */
   function createAggregate(string $name, callable $step, callable $finalize, int $numArgs = -1)
   {
      if (null === $this->connect) {
         $this->connectDB();
      }
      $this->connect->sqliteCreateAggregate($name, $step, $finalize, $numArgs);
      return $this;
   }

   /**
    * @see https://www.php.net/manual/ru/pdo-sqlite.createcollation.php
    * @param callable(string $string1,string $string2):int $callback
    * @return static
    */
   function createCollation(string $name, callable $callback)
   {
      if (null === $this->connect) {
         $this->connectDB();
      }
      $this->connect->sqliteCreateCollation($name, $callback);
      return $this;
   }

   /**
    * @return (array{type:string,name:string,tbl_name:string,rootpage:int,sql:string})[]
    */
   function master(?string $type = null, ?string $name = null, ?string $tblName = null): array
   {
      $opts  = [];
      $where = [];

      if ($type) {
         $opts['type'] = $type;
         $where[] = 'type = {type}';
      }
      if ($name) {
         $opts['name'] = $name;
         $where[] = 'name = {name}';
      }
      if ($tblName) {
         $opts['tbl_name'] = $tblName;
         $where[] = 'tbl_name = {tbl_name}';
      }

      $sql = 'SELECT * FROM sqlite_master';

      if ($opts) {
         $sql .= ' WHERE ' . \implode(' AND ', $where);
      }

      /** @var (array{type:string,name:string,tbl_name:string,rootpage:int,sql:string})[] $result */
      $result = $this->exec($sql, $opts, self::FETCH_ALL);

      return $result;
   }

   /**
    * @return (array{name:string,seq:int})[]
    */
   function sequence(): array
   {
      /** @var (array{name:string,seq:int})[] $result */
      $result = $this->exec('SELECT * FROM sqlite_sequence', [], self::FETCH_ALL);

      return $result;
   }

   /**
    * @return string[]
    */
   function pragmaCompileOptions(): array
   {
      /** @var (array{_: string})[] $options */
      $options = $this->exec('SELECT compile_options as "0" FROM pragma_compile_options', [], self::FETCH_ALL);
      return \array_column($options, 0);
   }

   /**
    * @return static
    */
   function vacuumInto(string $pathToFile)
   {
      $this->exec('VACUUM INTO {file};', [
         'file' => $pathToFile,
      ]);
      return $this;
   }

   /**
    * alias vacuumInto()
    * @return static
    */
   function backupToFile(string $pathToFile)
   {
      return $this->vacuumInto($pathToFile);
   }

   /**
    * В момент создания PDO может выбросить исключение \PDOException
    * @throws IPDOException
    * @throws \PDOException
    * @phpstan-assert !null $this->connect
    */
   protected function connectDB(): void
   {
      if (null !== $this->connect) {
         return;
      }

      if (\strpos($this->nameDB, 'sqlite:') === 0) {
         $this->nameDB = Util::replaceFirst('sqlite:', '', $this->nameDB);
      }

      if (\strpos($this->nameDB, ':memory:') === 0) {
         // skip
      }
      // 
      elseif (\strpos($this->nameDB, 'file:') === 0) {
         if (\PHP_VERSION_ID < 80100) {
            throw new IPDOException([
               'message' => \sprintf(
                  'IPDO: URI not supported "%s". PHP >=8.1',
                  $this->nameDB,
               ),
            ]);
         }
      }
      // 
      elseif (!\is_file($this->nameDB)) {
         throw new IPDOException([
            'message' => \sprintf(
               'IPDO: File not found "%s"',
               $this->nameDB,
            ),
         ]);
      }

      if (!static::extensionLoaded()) {
         throw new IPDOException([
            'message' => 'IPDO: Extension not loaded "pdo_sqlite"',
         ]);
      }

      $this->countConnect++;
      $this->connect = new \PDO(
         'sqlite:' . $this->nameDB,
         null,
         null,
         $this->options
      );
   }

   // ---------------------------------------------
   // 
   // ---------------------------------------------

   static function extensionLoaded(): bool
   {
      return \extension_loaded('pdo_sqlite');
   }
}
