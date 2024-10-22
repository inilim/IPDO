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
   public function __construct(string $pathToFile, array $options = [])
   {
      $this->nameDB  = $pathToFile;
      $this->options = $options;
   }

   /**
    * В момент создания PDO может выбросить исключение \PDOException
    * @throws IPDOException
    * @throws \PDOException
    */
   protected function connectDB(): void
   {
      if ($this->connect !== null) return;

      if ($this->nameDB !== ':memory:' && !\is_file($this->nameDB)) {
         throw new IPDOException(\sprintf(
            'IPDO: File not found "%s"',
            $this->nameDB,
         ));
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
