<?php

namespace Inilim\IPDO;

use Inilim\IPDO\IPDO;
use Inilim\IPDO\Exception\SQLiteNotFoundFileException;
use PDO;

class IPDOSQLite extends IPDO
{
   /**
    * @param string $path_to_file "path/to/file" OR ":memory:"
    */
   public function __construct(string $path_to_file)
   {
      $this->name_db = $path_to_file;
   }

   /**
    * В момент создания PDO может выбросить исключение PDOException
    * @throws PDOException
    */
   protected function connectDB(): void
   {
      if (!is_null($this->connect)) return;

      if ($this->name_db !== ':memory:' && !is_file($this->name_db)) {
         throw new SQLiteNotFoundFileException(sprintf('File not found "%s"', $this->name_db));
      }

      $this->count_connect++;
      $this->connect = new PDO(
         'sqlite:' . $this->name_db
      );
   }
}
