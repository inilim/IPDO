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

      $this->countConnect++;
      $this->connect = new \PDO(
         'sqlite:' . $this->nameDB,
         null,
         null,
         $this->options
      );
   }
}
