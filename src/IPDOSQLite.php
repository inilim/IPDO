<?php

namespace Inilim\IPDO;

use Inilim\IPDO\IPDO;
use Inilim\IPDO\Exception\SQLiteNotFoundFileException;
use Inilim\Integer\Integer;
use Inilim\Array\Array_;
use PDO;
use PDOException;

class IPDOSQLite extends IPDO
{
   /**
    * @param string $path_to_file "path/to/file" OR ":memory:"
    */
   public function __construct(string $path_to_file, Integer $integer, Array_ $array, array $options = [])
   {
      $this->name_db = $path_to_file;
      $this->integer = $integer;
      $this->array   = $array;
      $this->options = $options;
   }

   /**
    * В момент создания PDO может выбросить исключение PDOException
    * @throws SQLiteNotFoundFileException
    * @throws PDOException
    */
   protected function connectDB(): void
   {
      if ($this->connect !== null) return;

      if ($this->name_db !== ':memory:' && !\is_file($this->name_db)) {
         throw new SQLiteNotFoundFileException(\sprintf(
            'File not found "%s"',
            $this->name_db,
         ));
      }

      $this->count_connect++;
      $this->connect = new PDO(
         dsn: 'sqlite:' . $this->name_db,
         options: $this->options
      );
   }
}
