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

   /**
    * get version sqlite
    */
   function getVersion(): string
   {
      return \strval($this->exec('SELECT sqlite_version() AS ver', 1)['ver']);
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

      return $this->exec($sql, $opts, 2);
   }

   /**
    * @return (array{name:string,seq:int})[]
    */
   function sequence(): array
   {
      return $this->exec('SELECT * FROM sqlite_sequence', [], 2);
   }

   /**
    * @return string[]
    */
   function pragmaCompileOptions(): array
   {
      $options = $this->exec('SELECT compile_options as _ FROM pragma_compile_options', [], 2);
      return \array_column($options, '_');
   }

   /**
    * В момент создания PDO может выбросить исключение \PDOException
    * @throws IPDOException
    * @throws \PDOException
    */
   protected function connectDB(): void
   {
      if ($this->connect !== null) {
         return;
      }

      if ($this->nameDB !== ':memory:' && !\is_file($this->nameDB)) {
         throw new IPDOException([
            'message' => \sprintf(
               'IPDO: File not found "%s"',
               $this->nameDB,
            ),
         ]);
      }

      if (!self::extensionLoaded()) {
         throw new IPDOException([
            'message' => 'IPDO: Extensoin not loaded "pdo_sqlite"',
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
